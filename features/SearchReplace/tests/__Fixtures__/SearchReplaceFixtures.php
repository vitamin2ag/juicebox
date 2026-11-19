<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Tests\__Fixtures__;

use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Statamic\Auth\File\User;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\GlobalVariables;
use Statamic\Facades\Site;
use Statamic\Facades\Term as Terms;
use Statamic\Facades\YAML;
use Statamic\Globals\GlobalSet;
use Statamic\Globals\Variables;
use Statamic\Revisions\Revision;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy;
use Statamic\Taxonomies\Term;
use Statamic\Testing\Concerns\FakesRoles;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Batch;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Patch;
use Vitamin2\Juicebox\Features\SearchReplace\Searching\Searcher;

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

    /**
     * Saves a user that may edit everything, with a fixed id that a batch can name.
     */
    public static function editor(): string
    {
        $user = new User;
        $user->id('editor-1');
        $user->email('editor-1@example.com');
        $user->makeSuper();
        $user->save();

        return 'editor-1';
    }

    /**
     * A pending batch that replaces every match of the search, made as the control panel makes it.
     */
    public static function batch(
        string $search = 'Team',
        string $replacement = 'Praxis',
        ?string $user = null,
        string $id = '9d3c1f2a-0b4e-4c6d-8e7f-000000000001',
        string $createdAt = '2026-11-19 10:00:00',
    ): Batch {
        $occurrences = resolve(Searcher::class)->search($search, self::superUser())->occurrences;

        return new Batch(
            $id,
            $user,
            $search,
            $replacement,
            false,
            CarbonImmutable::parse($createdAt),
            Patch::fromOccurrences($occurrences, $replacement),
        );
    }

    /**
     * The values an entry, term or global set holds in one site, without those it inherits, as the Stache has them.
     *
     * @param  'entry'|'term'|'global'  $type
     * @return array<mixed>
     */
    public static function values(string $type, string $id, string $site = 'de'): array
    {
        $content = match ($type) {
            'entry' => Entries::find($id),
            'term' => Terms::all()
                ->toBase()
                ->whereInstanceOf(LocalizedTerm::class)
                ->first(fn (LocalizedTerm $term): bool => $term->id() === $id && $term->locale() === $site),
            'global' => GlobalVariables::find("{$id}::{$site}"),
        };

        $data = $content?->data();

        return $data instanceof \Illuminate\Support\Collection ? $data->all() : [];
    }

    /**
     * Saves unpublished changes of the entry, as the control panel does for a collection with revisions.
     */
    public static function workingCopy(Entry $entry): void
    {
        $revision = $entry->makeWorkingCopy();

        if ($revision instanceof Revision) {
            $revision->save();
        }
    }

    /**
     * The values of an entry as its file holds them.
     *
     * @return array<mixed>
     */
    public static function savedEntry(string $id): array
    {
        $path = Entries::find($id)?->path();

        return is_string($path) ? YAML::parse(File::get($path)) : [];
    }

    /**
     * The cron expression of every scheduled run of the apply command, and whether it may overlap.
     *
     * @return array<int, array{string, bool}>
     */
    public static function scheduledApplies(): array
    {
        return collect(resolve(Schedule::class)->events())
            ->filter(fn (Event $event): bool => str_ends_with($event->command ?? '', 'statamic:juicebox:search-replace:apply'))
            ->map(fn (Event $event): array => [$event->expression, $event->withoutOverlapping])
            ->values()
            ->all();
    }
}
