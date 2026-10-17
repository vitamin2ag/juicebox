<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__;

use Illuminate\Support\Facades\File;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectStore;

class RedirectFixtures
{
    public static function path(string $file = ''): string
    {
        return __DIR__.'/redirects/'.$file;
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
}
