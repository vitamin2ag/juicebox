<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Data;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Override;
use Statamic\Data\ExistsAsFile;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as Entries;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectStore;

class Redirect
{
    use ExistsAsFile;

    public function __construct(
        private readonly string $id,
        private readonly string $site,
        private readonly ?string $source = null,
        private readonly ?string $destination = null,
        private readonly int $type = 301,
        private readonly string $matchType = 'exact',
        private readonly bool $enabled = true,
        private readonly ?string $description = null,
        private readonly ?int $order = null,
    ) {}

    /**
     * An exact source is compared normalised, as it matches, and a regex one as written, since
     * normalising a pattern changes what it matches. A redirect without a source gets a key
     * no source has.
     */
    public static function sourceKeyOf(string $site, string $matchType, ?string $source): string
    {
        return $matchType === 'exact' && $source !== null
            ? self::exactKey($site, $source)
            : "{$site}|{$matchType}|{$source}";
    }

    /**
     * The pattern a regex source matches with, or null when there is no source or it does not compile.
     * An empty pattern compiles and matches every URL, so it is refused. Grepping no subjects only
     * compiles the pattern.
     */
    public static function pattern(?string $source): ?string
    {
        $pattern = self::rawPattern($source);

        return $pattern === null || @preg_grep($pattern, []) === false ? null : $pattern;
    }

    /**
     * The pattern of pattern() without the check that it compiles, for matching, where preg_match
     * compiles it anyway and does not match when it fails.
     */
    public static function rawPattern(?string $source): ?string
    {
        return $source === null || $source === '' ? null : "`{$source}`i";
    }

    /**
     * The types the blueprint of a redirect offers.
     *
     * @return array<int, int>
     */
    public static function types(): array
    {
        return [301, 302, 410];
    }

    /**
     * The destination that points to an entry, which follows the entry when its URI changes.
     */
    public static function toEntry(Entry $entry): string
    {
        return 'entry::'.$entry->id();
    }

    /**
     * The entry an `entry::<id>` destination points to, or null for any other destination and for a missing entry.
     */
    public static function entryOf(?string $destination): ?Entry
    {
        return $destination !== null && str_starts_with($destination, 'entry::')
            ? Entries::find(Str::after($destination, 'entry::'))
            : null;
    }

    public static function exactKey(string $site, string $url): string
    {
        return "{$site}|exact|".self::normalise($url);
    }

    public static function regexKey(string $site): string
    {
        return "{$site}|regex";
    }

    /**
     * Drops the slashes before the query string and at the end, and starts the URL with one.
     */
    public static function normalise(string $url): string
    {
        return Str::of($url)
            ->replaceMatches('#/+(?=\?|$)#', '')
            ->start('/')
            ->value();
    }

    public function id(): string
    {
        return $this->id;
    }

    public function site(): string
    {
        return $this->site;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function source(): ?string
    {
        return $this->source;
    }

    public function destination(): ?string
    {
        return $this->destination;
    }

    public function type(): int
    {
        return $this->type;
    }

    public function matchType(): string
    {
        return $this->matchType;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function order(): ?int
    {
        return $this->order;
    }

    /**
     * The key of the `lookup_key` Stache index (Statamic finds it by this method name), which is
     * null for a redirect that can not match, so a 404 reads one index instead of every redirect.
     */
    public function lookupKey(): ?string
    {
        if (! $this->enabled || $this->source === null) {
            return null;
        }

        return match ($this->matchType) {
            'exact' => self::exactKey($this->site, $this->source),
            'regex' => self::regexKey($this->site),
            default => null,
        };
    }

    /**
     * The key of the `source_key` Stache index (Statamic finds it by this method name): a source
     * redirects once per site and match type, enabled or not.
     */
    public function sourceKey(): string
    {
        return self::sourceKeyOf($this->site, $this->matchType, $this->source);
    }

    /**
     * The row of the list in the `listing_row` Stache index (Statamic finds it by this method name), so the
     * list reads one index instead of every redirect.
     *
     * @return array<string, mixed>
     */
    public function listingRow(): array
    {
        return [...$this->fileData(), 'site' => $this->site];
    }

    public function withDestination(?string $destination): self
    {
        return clone ($this, ['destination' => $destination]);
    }

    public function withEnabled(bool $enabled): self
    {
        return clone ($this, ['enabled' => $enabled]);
    }

    public function withOrder(?int $order): self
    {
        return clone ($this, ['order' => $order]);
    }

    /**
     * Keeps the id, site and order, and the file the redirect was read from, so saving it replaces that file.
     */
    public function withValues(?string $source, ?string $destination, int $type, string $matchType, bool $enabled, ?string $description): self
    {
        return clone ($this, [
            'source' => $source,
            'destination' => $destination,
            'type' => $type,
            'matchType' => $matchType,
            'enabled' => $enabled,
            'description' => $description,
        ]);
    }

    #[Override]
    public function path(): string
    {
        return resolve(RedirectStore::class)->directory()."{$this->site}/{$this->id}.yaml";
    }

    /**
     * Deletes the file the redirect was read from, which is not at path() when its name differs, as in `<order>_<id>.yaml`.
     */
    public function deleteFile(): void
    {
        $file = $this->initialPath();

        File::delete(is_string($file) ? $file : $this->path());
    }

    /**
     * @return array<string, mixed>
     */
    public function fileData(): array
    {
        return [
            'id' => $this->id,
            'enabled' => $this->enabled,
            'source' => $this->source,
            'destination' => $this->destination,
            'type' => $this->type,
            'match_type' => $this->matchType,
            'description' => $this->description,
            'order' => $this->order,
        ];
    }
}
