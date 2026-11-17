<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\DeadLinks\Tests\__Fixtures__;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Statamic\Assets\AssetContainer;
use Statamic\Auth\File\User;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Site;
use Statamic\Facades\YAML;
use Statamic\Globals\GlobalSet;
use Statamic\Globals\Variables;
use Statamic\Structures\CollectionTree;
use Statamic\Structures\Nav;
use Statamic\Structures\NavTree;
use Statamic\Taxonomies\Taxonomy;
use Statamic\Taxonomies\Term;
use Statamic\Testing\Concerns\FakesRoles;

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
            'de' => ['name' => 'Deutsch', 'url' => '/', 'locale' => 'de_CH'],
            'en' => ['name' => 'English', 'url' => '/en/', 'locale' => 'en_US'],
        ]);
    }

    /**
     * Saves an entry of a pages collection in both sites, as a localization of its origin when one is given.
     *
     * @param  array<mixed>  $data
     */
    public static function entry(string $id, array $data, string $site = 'de', ?string $origin = null, bool $published = true): Entry
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
        $entry->published($published);

        if ($origin !== null) {
            $entry->origin($origin);
        }

        $entry->save();

        return $entry;
    }

    /**
     * Gives the pages collection a structure that holds the given entries in the default site, so Statamic finds
     * them by their URL as pages of the structure.
     *
     * @param  array<int, string>  $ids
     */
    public static function structure(array $ids): void
    {
        $pages = new Collection;
        $pages->handle('pages');
        $pages->sites(['de', 'en']);
        $pages->routes('/{slug}');
        $pages->structureContents(['root' => false]);
        $pages->save();

        $tree = new CollectionTree;
        $tree->handle('pages');
        $tree->locale('de');
        $tree->tree(array_map(fn (string $id): array => ['entry' => $id], $ids));
        $tree->syncOriginal();
        $tree->save();
    }

    /**
     * Saves an asset container on a fake disk that holds the given files.
     *
     * @param  array<int, string>  $paths
     */
    public static function assets(string $handle, array $paths): AssetContainer
    {
        Storage::fake($handle);

        foreach ($paths as $path) {
            Storage::disk($handle)->put($path, '');
        }

        $container = new AssetContainer;
        $container->handle($handle);
        $container->disk($handle);
        $container->save();

        return $container;
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
     * The cron expression and the overlap switch of every scheduled event that runs the check.
     *
     * @return array<int, array{string, bool}>
     */
    public static function scheduledChecks(): array
    {
        return collect(resolve(Schedule::class)->events())
            ->filter(fn (Event $event): bool => str_ends_with($event->command ?? '', 'statamic:juicebox:dead-links:check'))
            ->map(fn (Event $event): array => [$event->expression, $event->withoutOverlapping])
            ->values()
            ->all();
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

    /**
     * Reads the blueprints of the pages collection, the tags taxonomy and the footer global set from
     * __Fixtures__/blueprints.
     */
    public static function blueprints(): void
    {
        Blueprint::setDirectory(__DIR__.'/blueprints');
    }

    /**
     * Statamic's cp_route() declares no return type, so the tests build control panel URLs with route().
     */
    public static function cpUrl(string $name, mixed $parameters = []): string
    {
        return route("statamic.cp.{$name}", $parameters);
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
        }->setTestRoles(['dead-links' => ['access cp', ...$permissions]]);

        $user = new User;
        $user->email('editor@example.com');
        $user->assignRole('dead-links');
        $user->save();

        return $user;
    }
}
