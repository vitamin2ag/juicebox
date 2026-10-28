<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Import;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use SplFileObject;
use Statamic\Facades\Site as Sites;
use Statamic\Sites\Site;
use UnexpectedValueException;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;

class CsvImporter
{
    public function __construct(private readonly RedirectRepository $redirects) {}

    /**
     * Reads the columns of the rias/statamic-redirect import. A row replaces the redirect with
     * its source and site, keeping its id and order, and a row without source, type or match
     * type, without destination unless its type is 410, or with a regex source that does not
     * compile, is skipped.
     */
    public function import(string $path, string $delimiter = ','): ImportResult
    {
        $rows = $this->rows($path, $delimiter);
        $existing = $this->redirects->all()->keyBy(fn (Redirect $redirect): string => $this->key($redirect->site(), $redirect->source()));
        $imported = 0;

        foreach ($rows as $row) {
            $redirect = $this->redirect($row, $existing);

            if ($redirect instanceof Redirect) {
                $this->redirects->save($redirect);
                $existing->put($this->key($redirect->site(), $redirect->source()), $redirect);
                $imported++;
            }
        }

        return new ImportResult(imported: $imported, skipped: $rows->count() - $imported);
    }

    /**
     * As in rias, a short row gets empty cells and a long one loses the cells without a column.
     *
     * @return Collection<int, Collection<string, string>>
     */
    private function rows(string $path, string $delimiter): Collection
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl($delimiter, escape: '');

        $lines = Collection::make($file)->map(fn (mixed $line): Collection => Collection::wrap($line)->filter(fn (mixed $cell): bool => is_string($cell)));

        // Excel starts a UTF-8 file with a byte order mark, which the reader of rias drops.
        $columns = ($lines->shift() ?? new Collection)->map(fn (string $column): string => Str::chopStart($column, "\u{FEFF}"))->flip();

        return $lines->map(fn (Collection $cells): Collection => $columns->map(fn (int $index): string => $cells->get($index, '')));
    }

    /**
     * @param  Collection<string, string>  $row
     * @param  Collection<string, Redirect>  $existing
     */
    private function redirect(Collection $row, Collection $existing): ?Redirect
    {
        $source = $this->value($row, 'source');
        $destination = $this->value($row, 'destination');
        $type = filter_var($row->get('type'), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        $matchType = $this->value($row, 'match_type');

        if ($source === null || $type === null || $matchType === null || ($destination === null && $type !== 410)) {
            return null;
        }

        if ($matchType === 'regex' && $this->redirects->pattern($source) === null) {
            return null;
        }

        $site = $this->value($row, 'site') ?? $this->string(Collection::wrap(Sites::current())->whereInstanceOf(Site::class)->firstOrFail()->handle());
        $replaced = $existing->get($this->key($site, $source));

        return new Redirect(
            id: $replaced?->id() ?? Str::uuid()->toString(),
            site: $site,
            source: $source,
            destination: $destination,
            type: $type,
            matchType: $matchType,
            enabled: filter_var($row->get('enabled', '1'), FILTER_VALIDATE_BOOL),
            description: $this->value($row, 'description') ?? $replaced?->description(),
            order: $replaced?->order(),
        );
    }

    /**
     * @param  Collection<string, string>  $row
     */
    private function value(Collection $row, string $column): ?string
    {
        $value = $row->get($column, '');

        return $value === '' ? null : $value;
    }

    private function key(string $site, ?string $source): string
    {
        return "{$site}|{$source}";
    }

    /**
     * Statamic's site methods return strings but declare no type.
     */
    private function string(mixed $value): string
    {
        return is_string($value) ? $value : throw new UnexpectedValueException('Statamic returned no string.');
    }
}
