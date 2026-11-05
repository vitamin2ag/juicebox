<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Import;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use SplFileObject;
use Statamic\Facades\Site as Sites;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;

class CsvImporter
{
    public function __construct(private readonly RedirectRepository $redirects) {}

    /**
     * Reads a CSV file whose first row names the columns. A row replaces the redirect with its
     * site, match type and source (see Redirect::sourceKey()), keeping its id and order.
     *
     * A row is skipped when it has no source, type or match type, when its type is not one the Redirects
     * accept, when its site does not exist, when it has no destination and its type is not 410, or when
     * its regex source does not compile.
     */
    public function import(string $path, string $delimiter = ','): ImportResult
    {
        $rows = $this->rows($path, $delimiter);
        $existing = $this->redirects->all()
            ->keyBy(fn (Redirect $redirect): string => $redirect->sourceKey());
        $orders = new Collection;
        $imported = 0;

        foreach ($rows as $row) {
            $redirect = $this->redirect($row, $existing, $orders);

            if ($redirect instanceof Redirect) {
                $this->redirects->save($redirect);
                $existing->put($redirect->sourceKey(), $redirect);
                $imported++;
            }
        }

        return new ImportResult(imported: $imported, skipped: $rows->count() - $imported);
    }

    /**
     * A short row gets empty cells and a long one loses the cells without a column.
     *
     * @return Collection<int, Collection<string, string>>
     */
    private function rows(string $path, string $delimiter): Collection
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl($delimiter, escape: '');

        $lines = Collection::make($file)->map(
            fn (mixed $line): Collection => Collection::wrap($line)
                ->filter(fn (mixed $cell): bool => is_string($cell)),
        );

        // Excel starts a UTF-8 file with a byte order mark, which is dropped.
        $columns = ($lines->shift() ?? new Collection)
            ->map(fn (string $column): string => Str::chopStart($column, "\u{FEFF}"))
            ->flip();

        return $lines->map(fn (Collection $cells): Collection => $columns->map(fn (int $index): string => $cells->get($index, '')));
    }

    /**
     * @param  Collection<string, string>  $row
     * @param  Collection<string, Redirect>  $existing
     * @param  Collection<string, int>  $orders
     */
    private function redirect(Collection $row, Collection $existing, Collection $orders): ?Redirect
    {
        $source = $this->value($row, 'source');
        $destination = $this->value($row, 'destination');
        $type = filter_var($row->get('type'), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        $matchType = $this->value($row, 'match_type');

        if ($source === null || $matchType === null || ! in_array($type, Redirect::types(), true)) {
            return null;
        }

        if ($destination === null && $type !== 410) {
            return null;
        }

        if ($matchType === 'regex' && Redirect::pattern($source) === null) {
            return null;
        }

        $site = $this->value($row, 'site') ?? Sites::current()->handle();

        if (! Sites::all()->has($site)) {
            return null;
        }

        $replaced = $existing->get(Redirect::sourceKeyOf($site, $matchType, $source));

        return new Redirect(
            id: $replaced?->id() ?? Str::uuid()->toString(),
            site: $site,
            source: $source,
            destination: $destination,
            type: $type,
            matchType: $matchType,
            enabled: filter_var($row->get('enabled', '1'), FILTER_VALIDATE_BOOL),
            description: $this->value($row, 'description') ?? $replaced?->description(),
            order: $replaced instanceof Redirect ? $replaced->order() : $this->nextOrder($site, $orders),
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

    /**
     * Asks the repository once per site, which loads every redirect of it, and counts on from there.
     *
     * @param  Collection<string, int>  $orders
     */
    private function nextOrder(string $site, Collection $orders): int
    {
        $order = $orders->get($site) ?? $this->redirects->nextOrder($site);
        $orders->put($site, $order + 1);

        return $order;
    }
}
