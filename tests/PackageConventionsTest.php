<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Statamic\Console\RunsInPlease;
use Vitamin2\Juicebox\FeatureServiceProvider;

/**
 * @return array<string, array{string}>
 */
function juiceboxFeatures(): array
{
    $features = [];

    foreach (glob(__DIR__.'/../features/*', GLOB_ONLYDIR) ?: [] as $directory) {
        $features[basename($directory)] = [basename($directory)];
    }

    return $features;
}

dataset('features', juiceboxFeatures(...));

dataset('feature commands', fn (): array => collect(juiceboxFeatures())
    ->flatMap(fn (array $feature): array => collect(glob(__DIR__.'/../features/'.$feature[0].'/src/Console/Commands/*.php') ?: [])
        ->mapWithKeys(fn (string $file): array => [$feature[0].' '.basename($file, '.php') => ['Vitamin2\\Juicebox\\Features\\'.$feature[0].'\\Console\\Commands\\'.basename($file, '.php')]])
        ->all())
    ->all());

dataset('features with config', fn (): array => array_filter(
    juiceboxFeatures(),
    fn (array $feature): bool => file_exists(__DIR__.'/../features/'.$feature[0].'/config/'.Str::snake($feature[0]).'.php'),
));

/**
 * Every PHP file the quality gates cover.
 *
 * @return Collection<int, SplFileInfo>
 */
function phpFiles(): Collection
{
    return collect(['src', 'features', 'tests'])
        ->flatMap(fn (string $directory): array => iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__."/../$directory", FilesystemIterator::SKIP_DOTS)), false))
        ->whereInstanceOf(SplFileInfo::class)
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php' && ! str_contains($file->getPathname(), 'node_modules'))
        ->values();
}

it('has the provider where feature discovery looks for it', function (string $feature): void {
    expect(__DIR__."/../features/$feature/src/ServiceProvider.php")->toBeFile();
})->with('features');

it('leaves feature providers to discovery instead of composer.json', function (string $feature): void {
    $composer = json_decode((string) file_get_contents(__DIR__.'/../composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect(data_get($composer, 'extra.laravel.providers'))
        ->not->toContain("Vitamin2\\Juicebox\\Features\\$feature\\ServiceProvider");
})->with('features');

it('extends the feature base provider in every feature', function (string $feature): void {
    expect("Vitamin2\\Juicebox\\Features\\$feature\\ServiceProvider")
        ->toExtend(FeatureServiceProvider::class);
})->with('features');

it('runs every command of a feature in please', function (string $command): void {
    expect($command)->toUseTrait(RunsInPlease::class);
})->with('feature commands');

it('ships a Boost skill for every feature', function (string $feature): void {
    expect(__DIR__.'/../resources/boost/skills/juicebox-'.Str::kebab($feature).'/SKILL.md')->toBeFile();
})->with('features');

it('documents every feature in docs/features and links it from the README', function (string $feature): void {
    $path = 'docs/features/'.Str::kebab($feature).'.md';

    expect(__DIR__.'/../'.$path)->toBeFile()
        ->and((string) file_get_contents(__DIR__.'/../README.md'))->toContain("]($path)");
})->with('features');

it('documents every config key and the publish command of every feature with config in its feature doc', function (string $feature): void {
    $doc = (string) file_get_contents(__DIR__.'/../docs/features/'.Str::kebab($feature).'.md');
    $config = require __DIR__."/../features/$feature/config/".Str::snake($feature).'.php';

    expect($doc)->toContain('php artisan vendor:publish --tag=juicebox-'.Str::kebab($feature).'-config');

    foreach (array_keys(is_array($config) ? $config : []) as $key) {
        expect($doc)->toContain("`$key`");
    }
})->with('features with config');

it('declares what its tests mutate for every feature', function (string $feature): void {
    $tests = collect(iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__."/../features/$feature/tests", FilesystemIterator::SKIP_DOTS)), false))
        ->whereInstanceOf(SplFileInfo::class);

    expect($tests->contains(fn (SplFileInfo $file): bool => str_contains((string) file_get_contents($file->getPathname()), 'mutates(')))->toBeTrue();
})->with('features');

it('keeps the changelog and release notes config the release workflow needs', function (): void {
    expect(__DIR__.'/../.github/release.yml')->toBeFile()
        ->and((string) file_get_contents(__DIR__.'/../CHANGELOG.md'))->toMatch('/^## \[Unreleased\]/m');
});

it('does not silence PHPStan', function (): void {
    expect(glob(__DIR__.'/../*baseline*'))->toBe([]);

    $neon = (string) file_get_contents(__DIR__.'/../phpstan.neon.dist');

    expect($neon)->not->toContain('baseline')->not->toContain('ignoreErrors');

    phpFiles()
        ->reject(fn (SplFileInfo $file): bool => $file->getFilename() === basename(__FILE__))
        ->each(function (SplFileInfo $file): void {
            expect((string) file_get_contents($file->getPathname()))->not->toContain('@phpstan-ignore');
        });
});
