<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Statamic\Facades\Permission;
use Statamic\Facades\Stache;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Http\Middleware\HandleNotFound;
use Vitamin2\Juicebox\Features\Redirects\ServiceProvider;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectStore;
use Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__\RedirectFixtures;

covers(ServiceProvider::class);

/*
 * The switch is read while the app boots, so these tests turn it off in beforeAll(), as a project does.
 */
beforeAll(function (): void {
    putenv('JUICEBOX_REDIRECTS_ENABLED=false');
});

afterAll(function (): void {
    putenv('JUICEBOX_REDIRECTS_ENABLED');
});

it('leaves the redirect store out of the Stache while the feature is disabled', function (): void {
    expect(Stache::stores()->has('redirects'))->toBeFalse()
        ->and(app()->bound(RedirectStore::class))->toBeFalse();
});

it('leaves the 404s of the :dataset middleware group alone while the feature is disabled', function (string $group): void {
    expect(resolve('router')->getMiddlewareGroups()[$group])->not->toContain(HandleNotFound::class);
})->with(['web', 'statamic.web']);

it('leaves redirects out of the classes the cache unserializes while the feature is disabled', function (): void {
    expect(config('cache.serializable_classes'))->not->toContain(Redirect::class);
});

it('leaves the control panel without redirects while the feature is disabled', function (): void {
    $this->actingAs(RedirectFixtures::superUser());

    expect(Route::has('statamic.cp.redirects.index'))->toBeFalse()
        ->and(Route::has('statamic.cp.redirects.import'))->toBeFalse()
        ->and(Route::has('statamic.cp.redirects.prune'))->toBeFalse()
        ->and(Collection::wrap(Permission::boot()->tree())->firstWhere('handle', 'redirects'))->toBeNull()
        ->and(array_column(RedirectFixtures::navigation(), 1))->not->toContain('Redirects');
});
