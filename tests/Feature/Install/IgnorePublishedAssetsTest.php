<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Vitamin2\Juicebox\Install\IgnorePublishedAssets;

covers(IgnorePublishedAssets::class);

beforeEach(function (): void {
    $this->site = sys_get_temp_dir().'/juicebox-site-'.getmypid();

    File::ensureDirectoryExists($this->site);
    app()->setBasePath($this->site);
    Process::path($this->site)->run(['git', 'init', '-q']);
});

afterEach(function (): void {
    File::deleteDirectory($this->site);
});

/**
 * The lines for the features with a built `resources/dist`; a new such feature adds its line here.
 */
function publishedAssetLines(): string
{
    return "/public/vendor/redirects/\n/public/vendor/schema-org/\n";
}

function runIgnorePublishedAssets(): string
{
    (new IgnorePublishedAssets)();

    return File::get(base_path('.gitignore'));
}

it('ignores the assets a feature publishes and nothing else', function (): void {
    expect(runIgnorePublishedAssets())->toBe(publishedAssetLines());
});

it('keeps what the site already ignores, with or without a final line break', function (string $contents): void {
    File::put(base_path('.gitignore'), $contents);

    expect(runIgnorePublishedAssets())->toBe("/vendor\n/storage\n".publishedAssetLines());
})->with(['/vendor
/storage
', '/vendor
/storage']);

it('adds a line only once', function (): void {
    runIgnorePublishedAssets();

    expect(runIgnorePublishedAssets())->toBe(publishedAssetLines());
});

it('leaves a site alone that already ignores the path, also by a broader rule', function (string $contents): void {
    File::put(base_path('.gitignore'), $contents);

    expect(runIgnorePublishedAssets())->toBe($contents);
})->with(["/public/vendor/redirects/  \n/public/vendor/schema-org/\n", "/public/vendor\n"]);

it('writes nothing when the site is no git repository', function (): void {
    File::deleteDirectory(base_path('.git'));

    (new IgnorePublishedAssets)();

    expect(base_path('.gitignore'))->not->toBeFile();
});

it('writes nothing when there is no git binary', function (): void {
    // A binary the system cannot find ends with exit code 127; Process does not throw.
    Process::fake(['*' => Process::result(exitCode: 127)]);

    (new IgnorePublishedAssets)();

    expect(base_path('.gitignore'))->not->toBeFile();
});
