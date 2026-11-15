<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Vitamin2\Juicebox\Features\Shortlinks\Data\Shortlink;
use Vitamin2\Juicebox\Features\Shortlinks\Http\Controllers\ShortlinkActionController;
use Vitamin2\Juicebox\Features\Shortlinks\Stache\ShortlinkRepository;
use Vitamin2\Juicebox\Features\Shortlinks\Tests\__Fixtures__\ShortlinkFixtures;

covers(ShortlinkActionController::class);

beforeAll(function (): void {
    ShortlinkFixtures::enable();
});

afterAll(function (): void {
    ShortlinkFixtures::disable();
});

beforeEach(function (): void {
    ShortlinkFixtures::isolate();
    $shortlinks = resolve(ShortlinkRepository::class);
    $shortlinks->save(new Shortlink(slug: 'sale', destination: '/angebot'));
    $shortlinks->save(new Shortlink(slug: 'team', destination: '/team'));
});

it('lists the actions of the selected shortlinks', function (): void {
    $response = $this->actingAs(ShortlinkFixtures::superUser())
        ->postJson(ShortlinkFixtures::cpUrl('shortlinks.actions.bulk'), ['selections' => ['sale', 'team']])
        ->assertOk();

    expect(Collection::wrap($response->json())->map(fn (mixed $action): mixed => data_get($action, 'handle'))->all())
        ->toContain('delete_shortlinks');
});

it('finds nothing for a selection that is no shortlink', function (string $route): void {
    $this->actingAs(ShortlinkFixtures::superUser())
        ->postJson(ShortlinkFixtures::cpUrl($route), ['action' => 'delete_shortlinks', 'selections' => ['unknown', 7], 'values' => []])
        ->assertNotFound();
})->with(['shortlinks.actions.bulk', 'shortlinks.actions.run']);

it('deletes the selected shortlinks', function (): void {
    $this->actingAs(ShortlinkFixtures::superUser())
        ->postJson(ShortlinkFixtures::cpUrl('shortlinks.actions.run'), ['action' => 'delete_shortlinks', 'selections' => ['sale', 'unknown'], 'values' => []])
        ->assertOk()
        ->assertJson(['success' => true, 'message' => 'Item deleted', 'callback' => ['removeFromSelections', ['sale']]]);

    expect(resolve(ShortlinkRepository::class)->all()->map(fn (Shortlink $shortlink): string => $shortlink->slug())->all())
        ->toBe(['team']);
});

it('refuses a user without the delete permission', function (): void {
    $this->actingAs(ShortlinkFixtures::userWith('view shortlinks', 'edit shortlinks'))
        ->postJson(ShortlinkFixtures::cpUrl('shortlinks.actions.run'), ['action' => 'delete_shortlinks', 'selections' => ['sale'], 'values' => []])
        ->assertForbidden();

    expect(resolve(ShortlinkRepository::class)->find('sale'))->toBeInstanceOf(Shortlink::class);
});
