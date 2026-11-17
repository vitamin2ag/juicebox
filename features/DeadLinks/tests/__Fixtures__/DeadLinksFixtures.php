<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\DeadLinks\Tests\__Fixtures__;

use Illuminate\Support\Facades\File;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\YAML;
use Statamic\Globals\GlobalSet;
use Statamic\Globals\Variables;
use Statamic\Structures\Nav;
use Statamic\Structures\NavTree;
use Statamic\Taxonomies\Taxonomy;
use Statamic\Taxonomies\Term;

class DeadLinksFixtures
{
    /**
     * The values of a content file in __Fixtures__/content, as Statamic stores them.
     *
     * @return array<mixed>
     */
    public static function data(string $name): array
    {
        return YAML::parse(File::get(__DIR__."/content/{$name}.yaml"));
    }

    public static function sites(): void
    {
        config(['statamic.system.multisite' => true]);
        Site::setSites([
            'de' => ['url' => '/', 'locale' => 'de_CH'],
            'en' => ['url' => '/en/', 'locale' => 'en_US'],
        ]);
    }

    /**
     * Saves an entry of a pages collection in both sites, as a localization of its origin when one is given.
     *
     * @param  array<mixed>  $data
     */
    public static function entry(string $id, array $data, string $site = 'de', ?string $origin = null): Entry
    {
        $pages = new Collection;
        $pages->handle('pages');
        $pages->sites(['de', 'en']);
        $pages->routes('/{slug}');
        $pages->save();

        $entry = new Entry;
        $entry->id($id);
        $entry->collection($pages);
        $entry->locale($site);
        $entry->slug($id);
        $entry->data($data);

        if ($origin !== null) {
            $entry->origin($origin);
        }

        $entry->save();

        return $entry;
    }

    /**
     * Saves a term of a tags taxonomy with its values per site.
     *
     * @param  array<string, array<mixed>>  $data
     */
    public static function term(string $slug, array $data): Term
    {
        $tags = new Taxonomy;
        $tags->handle('tags');
        $tags->sites(['de', 'en']);
        $tags->save();

        $term = new Term;
        $term->taxonomy($tags);
        $term->slug($slug);

        foreach ($data as $site => $values) {
            $term->dataForLocale($site, $values);
        }

        $term->save();

        return $term;
    }

    /**
     * Saves a global set with its variables per site.
     *
     * @param  array<string, array<mixed>>  $data
     */
    public static function globalSet(string $handle, array $data): GlobalSet
    {
        $set = new GlobalSet;
        $set->handle($handle);
        $set->sites(array_keys($data));
        $set->save();

        foreach ($data as $site => $values) {
            $variables = new Variables;
            $variables->globalSet($set);
            $variables->locale($site);
            $variables->data($values);
            $variables->save();
        }

        return $set;
    }

    /**
     * Saves a navigation with its tree per site.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $trees
     */
    public static function nav(string $handle, array $trees): Nav
    {
        $nav = new Nav;
        $nav->handle($handle);
        $nav->save();

        foreach ($trees as $site => $items) {
            $tree = new NavTree;
            $tree->handle($handle);
            $tree->locale($site);
            $tree->tree($items);
            $tree->save();
        }

        return $nav;
    }
}
