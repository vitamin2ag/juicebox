<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Statamic\Facades\Permission;
use Statamic\Facades\Stache;
use Statamic\Statamic;
use Vitamin2\Juicebox\Features\Shortlinks\Data\Shortlink;
use Vitamin2\Juicebox\Features\Shortlinks\ServiceProvider;
use Vitamin2\Juicebox\Features\Shortlinks\Stache\ShortlinkStore;
use Vitamin2\Juicebox\Features\Shortlinks\Tests\__Fixtures__\ShortlinkFixtures;

covers(ServiceProvider::class);

beforeAll(function (): void {
    ShortlinkFixtures::enable();
});

afterAll(function (): void {
    ShortlinkFixtures::disable();
});

it('registers the control panel build with Vite', function (): void {
    $feature = dirname(__DIR__);

    expect(Statamic::availableVites(request()))->toHaveKey('shortlinks', [
        'hotFile' => "{$feature}/resources/hot",
        'buildDirectory' => 'vendor/shortlinks/dist',
        'input' => 'resources/js/cp.ts',
    ]);
});

it('publishes the control panel build with the shortlinks tag', function (): void {
    $feature = dirname(__DIR__);
    $paths = LaravelServiceProvider::pathsToPublish(ServiceProvider::class, 'shortlinks');

    expect($paths)->toBe(["{$feature}/resources/dist/" => public_path('vendor/shortlinks/dist/')]);
});

it('keeps shortlinks in content/shortlinks by default', function (): void {
    app()->forgetInstance(ShortlinkStore::class);

    expect(resolve(ShortlinkStore::class)->directory())->toBe(base_path('content/shortlinks/'));
});

it('registers one shortlink store in the Stache', function (): void {
    expect(Stache::store('shortlinks'))->toBe(resolve(ShortlinkStore::class));
});

it('points the shortlink store at the configured directory', function (): void {
    config(['juicebox.shortlinks.store' => 'storage/app/shortlinks']);
    app()->forgetInstance(ShortlinkStore::class);

    expect(resolve(ShortlinkStore::class)->directory())->toBe(base_path('storage/app/shortlinks/'));
});

it('registers the shortlink permissions in a Shortlinks group', function (): void {
    $permission = fn (string $value, string $label, array $children = []): array => [
        'value' => $value,
        'label' => $label,
        'description' => null,
        'group' => 'shortlinks',
        'hidden_by' => [],
        'children' => $children,
    ];

    expect(Collection::wrap(Permission::boot()->tree())->firstWhere('handle', 'shortlinks'))->toBe([
        'handle' => 'shortlinks',
        'label' => 'Shortlinks',
        'permissions' => [
            $permission('view shortlinks', 'View Shortlinks', [
                $permission('edit shortlinks', 'Edit Shortlinks', [
                    $permission('create shortlinks', 'Create Shortlinks'),
                    $permission('delete shortlinks', 'Delete Shortlinks'),
                ]),
            ]),
        ],
    ]);
});

it('lists Shortlinks under Tools for a user who can view shortlinks', function (): void {
    $this->actingAs(ShortlinkFixtures::userWith('view shortlinks'));

    expect(ShortlinkFixtures::navigation())->toContain(['Tools', 'Shortlinks', ShortlinkFixtures::cpUrl('shortlinks.index'), 'link']);
});

it('leaves Shortlinks out of the navigation for a user who cannot view shortlinks', function (): void {
    $this->actingAs(ShortlinkFixtures::userWith('edit shortlinks', 'create shortlinks', 'delete shortlinks'));

    expect(array_column(ShortlinkFixtures::navigation(), 1))->not->toContain('Shortlinks');
});

it('lets the cache unserialize shortlinks', function (): void {
    $shortlink = new Shortlink(slug: 'sale', destination: '/kontakt');

    // The cache the Stache uses in an application: it serializes, and it unserializes only the classes of the list.
    $cache = Cache::build(['driver' => 'array', 'serialize' => true]);
    $cache->put('shortlink', $shortlink);

    expect($cache->get('shortlink'))->toEqual($shortlink)
        ->and(config('cache.serializable_classes'))->toBeArray();
});
