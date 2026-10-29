<?php

declare(strict_types=1);

use Illuminate\Support\Str;
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

dataset('features with config', fn (): array => array_filter(
    juiceboxFeatures(),
    fn (array $feature): bool => file_exists(__DIR__.'/../features/'.$feature[0].'/config/'.Str::snake($feature[0]).'.php'),
));

dataset('features with translations', fn (): array => array_filter(
    juiceboxFeatures(),
    fn (array $feature): bool => is_dir(__DIR__.'/../features/'.$feature[0].'/lang'),
));

dataset('translated locales', function (): array {
    $locales = [];

    foreach (array_keys(array_filter(juiceboxFeatures(), fn (array $feature): bool => is_dir(__DIR__.'/../features/'.$feature[0].'/lang'))) as $feature) {
        foreach (['de', 'de_CH', 'fr'] as $locale) {
            $locales["$feature $locale"] = [$feature, $locale];
        }
    }

    return $locales;
});

/**
 * @return list<array-key>
 */
function translationKeys(string $path): array
{
    expect($path)->toBeFile();

    $translations = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    return is_array($translations) ? array_keys($translations) : [];
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

it('translates every feature with a lang folder into de, de_CH and fr with the same keys', function (string $feature): void {
    $german = translationKeys(__DIR__."/../features/$feature/lang/de.json");

    expect($german)->not->toBeEmpty()
        ->and(translationKeys(__DIR__."/../features/$feature/lang/de_CH.json"))->toEqualCanonicalizing($german)
        ->and(translationKeys(__DIR__."/../features/$feature/lang/fr.json"))->toEqualCanonicalizing($german);
})->with('features with translations');

it('translates only what Statamic does not ship in :dataset', function (string $feature, string $locale): void {
    $ours = translationKeys(__DIR__."/../features/$feature/lang/$locale.json");
    $statamic = translationKeys(__DIR__."/../vendor/statamic/cms/lang/$locale.json");

    expect(array_values(array_intersect($ours, $statamic)))->toBeEmpty();
})->with('translated locales');

it('declares what its tests mutate for every feature', function (string $feature): void {
    $tests = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__."/../features/$feature/tests", FilesystemIterator::SKIP_DOTS));
    $declared = false;

    foreach ($tests as $file) {
        if ($file instanceof SplFileInfo && str_contains((string) file_get_contents($file->getPathname()), 'mutates(')) {
            $declared = true;
        }
    }

    expect($declared)->toBeTrue();
})->with('features');

it('keeps the changelog and release notes config the release workflow needs', function (): void {
    expect(__DIR__.'/../.github/release.yml')->toBeFile()
        ->and((string) file_get_contents(__DIR__.'/../CHANGELOG.md'))->toMatch('/^## \[Unreleased\]/m');
});

it('does not silence PHPStan', function (): void {
    expect(glob(__DIR__.'/../*baseline*'))->toBe([]);

    $neon = (string) file_get_contents(__DIR__.'/../phpstan.neon.dist');

    expect($neon)->not->toContain('baseline')->not->toContain('ignoreErrors');

    foreach (['src', 'features', 'tests'] as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__."/../$directory"));

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php' || $file->getFilename() === basename(__FILE__)) {
                continue;
            }

            expect((string) file_get_contents($file->getPathname()))->not->toContain('@phpstan-ignore');
        }
    }
});
