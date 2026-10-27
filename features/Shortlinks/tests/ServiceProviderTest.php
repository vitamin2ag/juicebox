<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Vitamin2\Juicebox\Features\Shortlinks\ServiceProvider;

mutates(ServiceProvider::class);

afterEach(function (): void {
    putenv('JUICEBOX_SHORTLINKS_ENABLED');
});

it('registers the provider through feature discovery', function (): void {
    expect(app()->getProviders(ServiceProvider::class))->toHaveCount(1);
});

it('disables the feature by default', function (): void {
    expect(config('juicebox.shortlinks.enabled'))->toBeFalse();
});

it('enables the feature through JUICEBOX_SHORTLINKS_ENABLED', function (): void {
    putenv('JUICEBOX_SHORTLINKS_ENABLED=true');
    config(['juicebox.shortlinks' => require __DIR__.'/../config/shortlinks.php']);

    expect(config('juicebox.shortlinks.enabled'))->toBeTrue();
});

it('publishes the config with the juicebox-shortlinks-config tag while the feature is disabled', function (): void {
    $paths = LaravelServiceProvider::pathsToPublish(ServiceProvider::class, 'juicebox-shortlinks-config');

    expect(config('juicebox.shortlinks.enabled'))->toBeFalse()
        ->and(array_values($paths))->toBe([config_path('juicebox/shortlinks.php')]);
});
