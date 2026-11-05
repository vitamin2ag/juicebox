<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Stache;

use Override;
use Statamic\Stache\Stores\BasicStore;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;

class RedirectStore extends BasicStore
{
    public const string LOOKUP_INDEX = 'lookup_key';

    public const string LISTING_INDEX = 'listing_row';

    public const string SOURCE_INDEX = 'source_key';

    #[Override]
    public function key(): string
    {
        return 'redirects';
    }

    /**
     * The site comes from the folder, keys the feature does not use are dropped, and an
     * empty or broken file becomes a disabled redirect without a source.
     *
     * @param  string  $path
     * @param  string|null  $contents
     */
    #[Override]
    public function makeItemFromFile(mixed $path, mixed $contents): Redirect
    {
        $data = $this->parse($contents);
        $type = filter_var($data['type'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        $redirect = new Redirect(
            id: $this->string($data, 'id') ?? basename($path, '.yaml'),
            site: basename(dirname($path)),
            source: $this->string($data, 'source'),
            destination: $this->string($data, 'destination'),
            type: in_array($type, Redirect::types(), true) ? $type : 301,
            matchType: $this->string($data, 'match_type') ?? 'exact',
            enabled: filter_var($data['enabled'] ?? false, FILTER_VALIDATE_BOOL),
            description: $this->string($data, 'description'),
            order: filter_var($data['order'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
        );

        $redirect->initialPath($path);

        return $redirect;
    }

    /**
     * @return array<mixed>
     */
    private function parse(?string $contents): array
    {
        if ($contents === null) {
            return [];
        }

        try {
            $data = Yaml::parse($contents);
        } catch (ParseException) {
            return [];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<mixed>  $data
     */
    private function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
