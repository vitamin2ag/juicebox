<?php

declare(strict_types=1);

use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Http\Controllers\PruneController;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;
use Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__\RedirectFixtures;

mutates(PruneController::class);

beforeEach(function (): void {
    RedirectFixtures::isolate();
});

/**
 * @return array<int, string>
 */
function redirectsLeftAfterPrune(): array
{
    return resolve(RedirectRepository::class)->all()
        ->map(fn (Redirect $redirect): string => $redirect->id())
        ->sort()
        ->values()
        ->all();
}

it('deletes the disabled redirects of every site', function (): void {
    RedirectFixtures::copyIntoStore();

    $this->actingAs(RedirectFixtures::superUser())
        ->post(RedirectFixtures::cpUrl('redirects.prune'))
        ->assertRedirect(RedirectFixtures::cpUrl('redirects.index'))
        ->assertSessionHas('success', 'Deleted 3 disabled redirects.');

    expect(redirectsLeftAfterPrune())->toBe([
        '0e5b1a52-1c1d-4b0e-9a51-3f2b9c6d7e01',
        '1f6c2b63-2d2e-4c1f-8b62-4a3cad7e8f02',
        '2a7d3c74-3e3f-4d20-9c73-5b4dbe8f9a03',
        '3b8e4d85-4f40-4e31-8d84-6c5ecf9a0b04',
        '4c9f5e96-5051-4f42-9e95-7d6fd0ab1c05',
    ]);
});

it('deletes one disabled redirect', function (): void {
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'disabled', site: 'de', source: '/alt', destination: '/neu', enabled: false));
    $redirects->save(new Redirect(id: 'enabled', site: 'de', source: '/anders', destination: '/neu'));

    $this->actingAs(RedirectFixtures::superUser())
        ->post(RedirectFixtures::cpUrl('redirects.prune'))
        ->assertSessionHas('success', 'Deleted 1 disabled redirect.');

    expect(redirectsLeftAfterPrune())->toBe(['enabled']);
});

it('says so when no redirect is disabled', function (): void {
    resolve(RedirectRepository::class)->save(new Redirect(id: 'enabled', site: 'de', source: '/alt', destination: '/neu'));

    $this->actingAs(RedirectFixtures::superUser())
        ->post(RedirectFixtures::cpUrl('redirects.prune'))
        ->assertSessionHas('success', 'No disabled redirects to delete.');

    expect(redirectsLeftAfterPrune())->toBe(['enabled']);
});

it('deletes with the delete redirects permission', function (): void {
    $this->actingAs(RedirectFixtures::userWith('delete redirects'))
        ->post(RedirectFixtures::cpUrl('redirects.prune'))
        ->assertRedirect(RedirectFixtures::cpUrl('redirects.index'));
});

it('forbids deleting without the delete redirects permission', function (): void {
    resolve(RedirectRepository::class)->save(new Redirect(id: 'disabled', site: 'de', source: '/alt', destination: '/neu', enabled: false));

    $this->actingAs(RedirectFixtures::userWith('view redirects', 'edit redirects', 'create redirects'))
        ->postJson(RedirectFixtures::cpUrl('redirects.prune'))
        ->assertForbidden();

    expect(redirectsLeftAfterPrune())->toBe(['disabled']);
});
