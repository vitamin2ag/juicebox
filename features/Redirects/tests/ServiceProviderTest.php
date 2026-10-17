<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Vitamin2\Juicebox\Features\Redirects\ServiceProvider;

mutates(ServiceProvider::class);

afterEach(function (): void {
    putenv('JUICEBOX_REDIRECTS_ENABLED');
});

it('registers the provider through feature discovery', function (): void {
    expect(app()->getProviders(ServiceProvider::class))->toHaveCount(1);
});

it('enables the feature by default', function (): void {
    expect(config('juicebox.redirects.enabled'))->toBeTrue();
});

it('publishes the config with the juicebox-redirects-config tag', function (): void {
    $paths = LaravelServiceProvider::pathsToPublish(ServiceProvider::class, 'juicebox-redirects-config');

    expect(array_values($paths))->toBe([config_path('juicebox/redirects.php')]);
});

it('disables the feature through JUICEBOX_REDIRECTS_ENABLED', function (): void {
    putenv('JUICEBOX_REDIRECTS_ENABLED=false');
    config(['juicebox.redirects' => require __DIR__.'/../config/redirects.php']);

    expect(config('juicebox.redirects.enabled'))->toBeFalse();
});
