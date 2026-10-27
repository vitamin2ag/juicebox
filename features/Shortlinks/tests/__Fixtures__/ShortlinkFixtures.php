<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Shortlinks\Tests\__Fixtures__;

use Illuminate\Support\Facades\File;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Stache;
use Vitamin2\Juicebox\Features\Shortlinks\Data\Shortlink;
use Vitamin2\Juicebox\Features\Shortlinks\Stache\ShortlinkStore;

class ShortlinkFixtures
{
    public static function path(string $file = ''): string
    {
        return __DIR__.'/shortlinks/'.$file;
    }

    /**
     * The feature is off by default and checked while the app boots, so tests switch it on in
     * beforeAll(), as a project does. A trait with defineEnvironment() would do the same, but
     * PHPStan skips traits that only Pest's uses() applies.
     */
    public static function enable(): void
    {
        putenv('JUICEBOX_SHORTLINKS_ENABLED=true');
    }

    public static function disable(): void
    {
        putenv('JUICEBOX_SHORTLINKS_ENABLED');
    }

    /**
     * The test case moves only the stores of the Stache into its per-process directory, and the
     * feature registers its store only while it is enabled. Tests that write shortlinks call this
     * first, so they fail here instead of writing into the real content/shortlinks.
     */
    public static function isolate(): void
    {
        expect(Stache::stores()->get('shortlinks'))->toBe(resolve(ShortlinkStore::class));
    }

    /**
     * Copies the fixtures into the store directory, which the test case keeps per process.
     */
    public static function copyIntoStore(): string
    {
        $directory = resolve(ShortlinkStore::class)->directory();

        File::copyDirectory(self::path(), $directory);

        return $directory;
    }

    public static function read(string $file, ?string $directory = null): Shortlink
    {
        $path = ($directory ?? self::path()).$file;

        return resolve(ShortlinkStore::class)->makeItemFromFile($path, File::get($path));
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
