<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Http\Controllers\RedirectActionController;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;
use Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__\RedirectFixtures;

mutates(RedirectActionController::class);

beforeEach(function (): void {
    RedirectFixtures::isolate();
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'on', site: 'de', source: '/an', destination: '/neu'));
    $redirects->save(new Redirect(id: 'off', site: 'de', source: '/aus', destination: '/neu', enabled: false));
});

it('lists the actions of the selected redirects', function (): void {
    $response = $this->actingAs(RedirectFixtures::superUser())
        ->postJson(RedirectFixtures::cpUrl('redirects.actions.bulk'), ['selections' => ['on', 'off']])
        ->assertOk();

    expect(Collection::wrap($response->json())->map(fn (mixed $action): mixed => data_get($action, 'handle'))->all())
        ->toContain('delete_redirects', 'disable_redirects', 'enable_redirects');
});

it('finds nothing for a selection that is no redirect', function (string $route): void {
    $this->actingAs(RedirectFixtures::superUser())
        ->postJson(RedirectFixtures::cpUrl($route), ['action' => 'delete_redirects', 'selections' => ['unknown', 7], 'values' => []])
        ->assertNotFound();
})->with(['redirects.actions.bulk', 'redirects.actions.run']);

it('runs an action on the selected redirects', function (): void {
    $this->actingAs(RedirectFixtures::superUser())
        ->postJson(RedirectFixtures::cpUrl('redirects.actions.run'), ['action' => 'enable_redirects', 'selections' => ['off', 'unknown'], 'values' => []])
        ->assertOk()
        ->assertJson(['success' => true, 'message' => 'Redirect enabled']);

    expect(resolve(RedirectRepository::class)->find('off')?->enabled())->toBeTrue();
});

it('deletes the selected redirects', function (): void {
    $this->actingAs(RedirectFixtures::superUser())
        ->postJson(RedirectFixtures::cpUrl('redirects.actions.run'), ['action' => 'delete_redirects', 'selections' => ['on'], 'values' => []])
        ->assertOk()
        ->assertJson(['success' => true, 'message' => 'Item deleted']);

    expect(resolve(RedirectRepository::class)->find('on'))->toBeNull();
});

it('refuses a user without the permission of the action', function (string $action): void {
    $this->actingAs(RedirectFixtures::userWith('view redirects', 'edit redirects'))
        ->postJson(RedirectFixtures::cpUrl('redirects.actions.run'), ['action' => $action, 'selections' => ['on'], 'values' => []])
        ->assertForbidden();

    expect(resolve(RedirectRepository::class)->find('on'))->toBeInstanceOf(Redirect::class);
})->with(['delete_redirects']);
