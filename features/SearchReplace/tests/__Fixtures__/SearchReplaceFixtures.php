<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Tests\__Fixtures__;

use Illuminate\Support\Facades\File;
use Statamic\Auth\File\User;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Site;
use Statamic\Facades\YAML;
use Statamic\Globals\GlobalSet;
use Statamic\Globals\Variables;
use Statamic\Taxonomies\Taxonomy;
use Statamic\Taxonomies\Term;
use Statamic\Testing\Concerns\FakesRoles;

class SearchReplaceFixtures
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
            'de' => ['name' => 'Deutsch', 'url' => '/', 'locale' => 'de_CH'],
            'en' => ['name' => 'English', 'url' => '/en/', 'locale' => 'en_US'],
        ]);
    }

    /**
     * Reads the blueprints of the pages collection, the tags taxonomy and the footer global set from
     * __Fixtures__/blueprints.
     */
    public static function blueprints(): void
    {
        Blueprint::setDirectory(__DIR__.'/blueprints');
    }

    /**
     * Saves an entry in both sites, as a localization of its origin when one is given. A collection other than pages
     * has no blueprint in __Fixtures__/blueprints, so its entries get Statamic's default one.
     *
     * @param  array<mixed>  $data
     */
    public static function entry(string $id, array $data, string $site = 'de', ?string $origin = null, string $collection = 'pages'): Entry
    {
        $pages = new Collection;
        $pages->handle($collection);
        $pages->sites(['de', 'en']);
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

    public static function superUser(): User
    {
        $user = new User;
        $user->email('super@example.com');
        $user->makeSuper();
        $user->save();

        return $user;
    }

    /**
     * Saves a control panel user whose only role has these permissions. The role is faked, so no roles file is
     * written.
     */
    public static function userWith(string ...$permissions): User
    {
        new class
        {
            use FakesRoles {
                setTestRoles as public;
            }
        }->setTestRoles(['search-replace' => ['access cp', ...$permissions]]);

        $user = new User;
        $user->email('editor@example.com');
        $user->assignRole('search-replace');
        $user->save();

        return $user;
    }
}
