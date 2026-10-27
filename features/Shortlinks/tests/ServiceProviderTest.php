<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Statamic\Facades\Stache;
use Vitamin2\Juicebox\Features\Shortlinks\ServiceProvider;
use Vitamin2\Juicebox\Features\Shortlinks\Stache\ShortlinkStore;

use function Pest\Laravel\get;

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

it('keeps shortlinks in content/shortlinks by default', function (): void {
    expect(resolve(ShortlinkStore::class)->directory())->toBe(base_path('content/shortlinks/'));
});

it('points the shortlink store at the configured directory', function (): void {
    config(['juicebox.shortlinks.store' => 'storage/app/shortlinks']);

    expect(resolve(ShortlinkStore::class)->directory())->toBe(base_path('storage/app/shortlinks/'));
});

it('leaves the shortlink store out of the Stache while the feature is disabled', function (): void {
    expect(Stache::stores()->has('shortlinks'))->toBeFalse();
});

it('leaves /s/ to the pages of the site while the feature is disabled', function (): void {
    expect(Route::has('shortlinks.redirect'))->toBeFalse();

    get('/s/sale')->assertNotFound();
});

it('registers the shortlink store in the Stache when the feature is enabled', function (): void {
    config(['juicebox.shortlinks.enabled' => true]);

    new ServiceProvider(app())->boot();

    expect(Stache::store('shortlinks'))->toBe(resolve(ShortlinkStore::class));
});
