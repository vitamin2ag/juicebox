<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Statamic\Facades\Permission;
use Statamic\Facades\Stache;
use Vitamin2\Juicebox\Features\Shortlinks\ServiceProvider;
use Vitamin2\Juicebox\Features\Shortlinks\Stache\ShortlinkStore;
use Vitamin2\Juicebox\Features\Shortlinks\Tests\__Fixtures__\ShortlinkFixtures;

mutates(ServiceProvider::class);

beforeAll(function (): void {
    ShortlinkFixtures::enable();
});

afterAll(function (): void {
    ShortlinkFixtures::disable();
});

it('registers the shortlink store in the Stache', function (): void {
    expect(Stache::store('shortlinks'))->toBe(resolve(ShortlinkStore::class));
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
