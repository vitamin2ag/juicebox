<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Graph;

use Statamic\Entries\Entry;
use Statamic\Facades\Site;
use Statamic\Support\Str;

/**
 * The only place `@id` fragments are spelled out. Every node identifier and
 * every cross-reference between nodes goes through here, so the graph's
 * URI scheme lives in one file.
 */
final class Ids
{
    public static function site(): string
    {
        return Site::current()->absoluteUrl();
    }

    public static function language(): string
    {
        return Site::current()->shortLocale();
    }

    public static function entryUrl(Entry $entry): ?string
    {
        return $entry->absoluteUrl();
    }

    /**
     * @param  string  $entityClass  Short class name of the configured
     *                               entity schema (e.g. `Organization`,
     *                               `Person`, `LocalBusiness`), as stored
     *                               on `json_ld_entity`.
     */
    public static function entity(string $entityClass = 'Organization'): string
    {
        return self::site().'#'.Str::lower($entityClass);
    }

    public static function website(): string
    {
        return self::site().'#website';
    }

    public static function webPage(?string $url = null): string
    {
        return self::fragment($url ?? self::site(), 'webpage');
    }

    public static function breadcrumb(?string $url = null): string
    {
        return self::fragment($url ?? self::site(), 'breadcrumb');
    }

    public static function forEntry(Entry $entry, string $fragment): string
    {
        return self::fragment(self::entryUrl($entry), $fragment);
    }

    /** @return array{'@id': string} */
    public static function ref(string $id): array
    {
        return ['@id' => $id];
    }

    private static function fragment(?string $url, string $fragment): string
    {
        return rtrim($url ?? '', '/')."#{$fragment}";
    }
}
