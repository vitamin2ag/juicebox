<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__;

use Illuminate\Support\Facades\File;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Stache;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectStore;

class RedirectFixtures
{
    public static function path(string $file = ''): string
    {
        return __DIR__.'/redirects/'.$file;
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
    public static function page(string $slug): void
    {
        $pages = new Collection;
        $pages->handle('pages');
        $pages->routes('/{slug}');
        $pages->save();

        $entry = new Entry;
        $entry->id($slug);
        $entry->collection($pages);
        $entry->slug($slug);
        $entry->save();
    }
}
