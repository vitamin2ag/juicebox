<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\DeadLinks\Scanning;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Finds the links in the values of an entry, term, global set or navigation item, through every nested set.
 */
class LinkExtractor
{
    /**
     * The links in the values, each with the top-level field that holds it, once per field.
     *
     * @return Collection<int, array{field: string, url: string}>
     */
    public function extract(mixed $values): Collection
    {
        return $this->items($values)
            ->flatMap(fn (mixed $value, int|string $field): Collection => $this->linksIn($value)
                ->map(fn (string $url): array => ['field' => "{$field}", 'url' => $url]))
            ->uniqueStrict()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function linksIn(mixed $value): Collection
    {
        if (is_string($value)) {
            return $this->linksInText($value);
        }

        return $this->items($value)
            ->flatMap(fn (mixed $item): Collection => $this->linksIn($item))
            ->merge($this->markLink($value));
    }

    /**
     * A whole value is a link when it is a reference, a URL with the http(s) scheme (even a mistyped one, such as
     * `https::/example.com`) or a path on the site; any other text is searched for the targets of HTML and Markdown
     * links and for bare URLs.
     *
     * @return Collection<int, string>
     */
    private function linksInText(string $text): Collection
    {
        if (preg_match('~^(entry::|asset::|statamic://|https?:|/(?!/))\S*$~i', $text) === 1) {
            return collect([$this->normalise($text)]);
        }

        preg_match_all('~(?|href\s*=\s*"([^"]*)"|href\s*=\s*\'([^\']*)\'|\]\(([^\s)]*))~i', $text, $targets);
        preg_match_all('~https?://[^\s<>"\'\[\]]+~i', $text, $urls);

        return collect($targets[1])
            ->merge(array_map(fn (string $url): string => rtrim($url, '.,;:!?)*'), $urls[0]))
            ->map(fn (string $url): string => html_entity_decode($url))
            ->reject($this->isSkipped(...))
            ->map($this->normalise(...));
    }

    /**
     * The target of a Bard link mark, which may be any URL the editor typed.
     *
     * @return Collection<int, string>
     */
    private function markLink(mixed $value): Collection
    {
        $href = data_get($value, 'attrs.href');

        return data_get($value, 'type') === 'link' && is_string($href) && ! $this->isSkipped($href)
            ? collect([$this->normalise($href)])
            : new Collection;
    }

    /**
     * Anchors, empty targets and other schemes than http(s) (mailto:, tel:, javascript:, ...) can not be checked.
     */
    private function isSkipped(string $url): bool
    {
        return $url === '' || str_starts_with($url, '#') || preg_match('~^(?!https?:|statamic:)[a-z][a-z\d+.-]*:(?!:)~i', $url) === 1;
    }

    /**
     * Bard links point to an entry or asset with a `statamic://` prefix that the link fieldtype leaves out.
     */
    private function normalise(string $url): string
    {
        return Str::chopStart($url, 'statamic://');
    }

    /**
     * Statamic hands the top-level values over as a collection and keeps nested ones as arrays.
     *
     * @return Collection<array-key, mixed>
     */
    private function items(mixed $value): Collection
    {
        if ($value instanceof Collection) {
            return $value;
        }

        return collect(is_array($value) ? $value : []);
    }
}
