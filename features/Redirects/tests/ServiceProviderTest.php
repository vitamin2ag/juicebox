<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Statamic\Facades\Addon;
use Statamic\Facades\Permission;
use Statamic\Facades\Stache;
use Statamic\Statamic;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Http\Middleware\HandleNotFound;
use Vitamin2\Juicebox\Features\Redirects\ServiceProvider;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectStore;
use Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__\RedirectFixtures;

mutates(ServiceProvider::class);

afterEach(function (): void {
    putenv('JUICEBOX_REDIRECTS_ENABLED');
    putenv('JUICEBOX_REDIRECTS_CREATE_ENTRY_REDIRECTS');
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

it('registers the control panel build with Vite', function (): void {
    $feature = dirname(__DIR__);

    expect(Statamic::availableVites(request()))->toHaveKey('redirects', [
        'hotFile' => "{$feature}/resources/hot",
        'buildDirectory' => 'vendor/redirects/dist',
        'input' => 'resources/js/cp.js',
    ]);
});

it('publishes the control panel build with the redirects tag', function (): void {
    $feature = dirname(__DIR__);
    $paths = LaravelServiceProvider::pathsToPublish(ServiceProvider::class, 'redirects');

    expect($paths)->toBe(["{$feature}/resources/dist/" => public_path('vendor/redirects/dist/')]);
});

it('disables the feature through JUICEBOX_REDIRECTS_ENABLED', function (): void {
    putenv('JUICEBOX_REDIRECTS_ENABLED=false');
    config(['juicebox.redirects' => require __DIR__.'/../config/redirects.php']);

    expect(config('juicebox.redirects.enabled'))->toBeFalse();
});

it('creates entry redirects by default', function (): void {
    expect(config('juicebox.redirects.create_entry_redirects'))->toBeTrue();
});

it('stops creating entry redirects through JUICEBOX_REDIRECTS_CREATE_ENTRY_REDIRECTS', function (): void {
    putenv('JUICEBOX_REDIRECTS_CREATE_ENTRY_REDIRECTS=false');
    config(['juicebox.redirects' => require __DIR__.'/../config/redirects.php']);

    expect(config('juicebox.redirects.create_entry_redirects'))->toBeFalse();
});

it('keeps redirects in content/redirects by default', function (): void {
    app()->forgetInstance(RedirectStore::class);

    expect(resolve(RedirectStore::class)->directory())->toBe(base_path('content/redirects/'));
});

it('registers one redirect store in the Stache', function (): void {
    expect(Stache::store('redirects'))->toBe(resolve(RedirectStore::class));
});

it('points the redirect store at the configured directory', function (): void {
    config(['juicebox.redirects.store' => 'storage/app/redirects']);
    app()->forgetInstance(RedirectStore::class);

    expect(resolve(RedirectStore::class)->directory())->toBe(base_path('storage/app/redirects/'));
});

it('handles the 404s of the :dataset middleware group first', function (string $group): void {
    expect(data_get(resolve('router')->getMiddlewareGroups(), [$group, 0]))->toBe(HandleNotFound::class);
})->with(['web', 'statamic.web']);

it('registers the permissions of rias/statamic-redirect in a Redirects group', function (): void {
    $permission = fn (string $value, string $label, array $children = []): array => [
        'value' => $value,
        'label' => $label,
        'description' => null,
        'group' => 'redirects',
        'hidden_by' => [],
        'children' => $children,
    ];

    expect(Collection::wrap(Permission::boot()->tree())->firstWhere('handle', 'redirects'))->toBe([
        'handle' => 'redirects',
        'label' => 'Redirects',
        'permissions' => [
            $permission('view redirects', 'View Redirects', [
                $permission('edit redirects', 'Edit Redirects', [
                    $permission('create redirects', 'Create Redirects'),
                    $permission('delete redirects', 'Delete Redirects'),
                ]),
            ]),
        ],
    ]);
});

it('lists Redirects under Tools for a user who can view redirects', function (): void {
    $this->actingAs(RedirectFixtures::userWith('view redirects'));

    expect(RedirectFixtures::navigation())->toContain(['Tools', 'Redirects', RedirectFixtures::cpUrl('redirects.index'), 'arrow-up-right']);
});

it('leaves Redirects out of the navigation for a user who cannot view redirects', function (): void {
    $this->actingAs(RedirectFixtures::userWith('edit redirects', 'create redirects', 'delete redirects'));

    expect(array_column(RedirectFixtures::navigation(), 1))->not->toContain('Redirects');
});

it('switches itself off while rias/statamic-redirect is installed', function (): void {
    $router = resolve('router');
    $router->removeMiddlewareFromGroup('web', HandleNotFound::class);
    $router->removeMiddlewareFromGroup('statamic.web', HandleNotFound::class);
    Stache::stores()->forget('redirects');
    Addon::shouldReceive('all')->andReturn(collect(['rias/statamic-redirect' => null]));

    new ReflectionMethod(ServiceProvider::class, 'bootFeature')->invoke(new ServiceProvider(app()));

    expect(config('juicebox.redirects.enabled'))->toBeFalse()
        ->and(Stache::stores()->has('redirects'))->toBeFalse()
        ->and($router->getMiddlewareGroups()['web'])->not->toContain(HandleNotFound::class)
        ->and($router->getMiddlewareGroups()['statamic.web'])->not->toContain(HandleNotFound::class);
});

it('lets the cache unserialize redirects', function (): void {
    expect(config('cache.serializable_classes'))->toContain(Redirect::class);
});
