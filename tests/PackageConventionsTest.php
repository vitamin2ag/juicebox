<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Vitamin2\Juicebox\Features\FeatureServiceProvider;
use Vitamin2\Juicebox\Tests\TestCase;

/**
 * @return array<string, array{string}>
 */
function juiceboxFeatures(): array
{
    $features = [];

    foreach (glob(__DIR__.'/../src/Features/*', GLOB_ONLYDIR) ?: [] as $directory) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                $features[basename($directory)] = [basename($directory)];

                break;
            }
        }
    }

    return $features;
}

dataset('features', juiceboxFeatures(...));

dataset('features with config', fn (): array => array_filter(
    juiceboxFeatures(),
    fn (array $feature): bool => file_exists(__DIR__.'/../config/'.Str::snake($feature[0]).'.php'),
));

it('has a provider for every feature registered in composer.json', function (string $feature): void {
    expect(TestCase::composerProviders())
        ->toContain("Vitamin2\\Juicebox\\Features\\$feature\\ServiceProvider");
})->with('features');

it('extends the feature base provider in every feature', function (string $feature): void {
    expect("Vitamin2\\Juicebox\\Features\\$feature\\ServiceProvider")
        ->toExtend(FeatureServiceProvider::class);
})->with('features');

it('ships a Boost skill for every feature', function (string $feature): void {
    expect(__DIR__.'/../resources/boost/skills/juicebox-'.Str::kebab($feature).'/SKILL.md')->toBeFile();
})->with('features');

it('documents the config publish tag of every feature in the README', function (string $feature): void {
    $readme = (string) file_get_contents(__DIR__.'/../README.md');

    expect($readme)->toContain('statamic-juicebox-'.Str::kebab($feature).'-config');
})->with('features with config');

it('does not silence PHPStan', function (): void {
    expect(glob(__DIR__.'/../*baseline*'))->toBe([]);

    $neon = (string) file_get_contents(__DIR__.'/../phpstan.neon.dist');

    expect($neon)->not->toContain('baseline')->not->toContain('ignoreErrors');

    foreach (['src', 'config', 'tests'] as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__."/../$directory"));

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php' || $file->getFilename() === basename(__FILE__)) {
                continue;
            }

            expect((string) file_get_contents($file->getPathname()))->not->toContain('@phpstan-ignore');
        }
    }
});
