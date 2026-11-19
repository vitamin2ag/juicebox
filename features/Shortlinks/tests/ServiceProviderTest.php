<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Statamic\Facades\Permission;
use Statamic\Facades\Stache;
use Vitamin2\Juicebox\Features\Shortlinks\Data\Shortlink;
use Vitamin2\Juicebox\Features\Shortlinks\ServiceProvider;
use Vitamin2\Juicebox\Features\Shortlinks\Stache\ShortlinkStore;
use Vitamin2\Juicebox\Features\Shortlinks\Tests\__Fixtures__\ShortlinkFixtures;

use function Pest\Laravel\get;

covers(ServiceProvider::class);

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

it('leaves the shortlink store out of the Stache while the feature is disabled', function (): void {
    expect(Stache::stores()->has('shortlinks'))->toBeFalse()
        ->and(app()->bound(ShortlinkStore::class))->toBeFalse();
});

it('leaves shortlinks out of the classes the cache unserializes while the feature is disabled', function (): void {
    expect(config('cache.serializable_classes'))->not->toContain(Shortlink::class);
});

it('leaves /s/ to the pages of the site while the feature is disabled', function (): void {
    expect(Route::has('shortlinks.redirect'))->toBeFalse();

    get('/s/sale')->assertNotFound();
});

it('leaves the control panel without shortlinks while the feature is disabled', function (): void {
    $this->actingAs(ShortlinkFixtures::superUser());

    expect(Route::has('statamic.cp.shortlinks.index'))->toBeFalse()
        ->and(Route::has('statamic.cp.shortlinks.qr'))->toBeFalse()
        ->and(Collection::wrap(Permission::boot()->tree())->firstWhere('handle', 'shortlinks'))->toBeNull()
        ->and(array_column(ShortlinkFixtures::navigation(), 1))->not->toContain('Shortlinks');
});
