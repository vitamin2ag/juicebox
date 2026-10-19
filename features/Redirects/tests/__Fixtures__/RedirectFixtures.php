<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__;

use Illuminate\Support\Collection as Items;
use Illuminate\Support\Facades\File;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\Stache;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\CollectionTree;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectStore;

class RedirectFixtures
{
    public static function path(string $file = ''): string
    {
        return __DIR__.'/redirects/'.$file;
    }

    public static function csv(string $file): string
    {
        return __DIR__.'/imports/'.$file;
    }

    /**
     * The test case moves only the stores of the Stache into its per-process directory. Tests
     * that write redirects call this first, so a mutant that leaves the store out of the Stache
     * fails here instead of writing into the real content/redirects.
     */
    public static function isolate(): void
    {
        expect(Stache::stores()->get('redirects'))->toBe(resolve(RedirectStore::class));
    }

    /**
     * Copies the fixtures into the store directory, which the test case keeps per process.
     */
    public static function copyIntoStore(): string
    {
        $directory = resolve(RedirectStore::class)->directory();

        File::copyDirectory(self::path(), $directory);

        return $directory;
    }

    public static function read(string $file, ?string $directory = null): Redirect
    {
        $path = ($directory ?? self::path()).$file;

        return resolve(RedirectStore::class)->makeItemFromFile($path, File::get($path));
    }

    /**
     * Saves an entry of a pages collection at /<slug>, with the slug as its id.
     */
    public static function page(string $slug, bool $published = true, ?string $site = null): Entry
    {
        $pages = new Collection;
        $pages->handle('pages');

        if ($site !== null) {
            $pages->sites([$site]);
        }

        $pages->routes('/{slug}');
        $pages->save();

        $entry = new Entry;
        $entry->id($slug);
        $entry->collection($pages);
        $entry->slug($slug);
        $entry->published($published);

        if ($site !== null) {
            $entry->locale($site);
        }

        $entry->save();

        return $entry;
    }

    /**
     * Saves a structured pages collection at /<parent uri>/<slug> with one entry per id in
     * the tree, the id also being its slug, and returns the tree to move them around in.
     *
     * @param  array<int, array{entry: string, children?: array<int, mixed>}>  $tree
     */
    public static function structure(array $tree): CollectionTree
    {
        $pages = new Collection;
        $pages->handle('pages');
        $pages->routes('{parent_uri}/{slug}');
        $pages->structureContents(['max_depth' => 3]);
        $pages->save();

        foreach (Items::wrap($tree)->flatten()->filter(fn (mixed $id): bool => is_string($id)) as $id) {
            $entry = new Entry;
            $entry->id($id);
            $entry->collection($pages);
            $entry->slug($id);
            $entry->save();
        }

        $collectionTree = Items::wrap(Items::wrap($pages->structure())->whereInstanceOf(CollectionStructure::class)->firstOrFail()->in('de'))
            ->whereInstanceOf(CollectionTree::class)
            ->firstOrFail();
        $collectionTree->tree($tree);
        $collectionTree->save();

        return $collectionTree;
    }

    /**
     * Gives a saved entry another slug the way the control panel does: read it, change it, save it.
     */
    public static function rename(string $id, string $slug): void
    {
        $entry = Items::wrap(Entries::find($id))->whereInstanceOf(Entry::class)->firstOrFail();
        $entry->slug($slug);
        $entry->save();
    }
}
