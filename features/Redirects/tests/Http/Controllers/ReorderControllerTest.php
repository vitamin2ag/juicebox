<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Http\Controllers\ReorderController;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectStore;
use Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__\RedirectFixtures;

mutates(ReorderController::class);

beforeEach(function (): void {
    RedirectFixtures::isolate();
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'a', site: 'de', source: '/a', destination: '/neu'));
    $redirects->save(new Redirect(id: 'b', site: 'de', source: '/b', destination: '/neu', order: 9));
    $redirects->save(new Redirect(id: 'c', site: 'de', source: '/c', destination: '/neu', order: 1));
});

/**
 * @return array<string, int|null>
 */
function orders(): array
{
    return resolve(RedirectRepository::class)->all()
        ->mapWithKeys(fn (Redirect $redirect): array => [$redirect->id() => $redirect->order()])
        ->sortKeys()
        ->all();
}

it('numbers the redirects in the order they come in, from 1', function (): void {
    $this->actingAs(RedirectFixtures::superUser())
        ->post(RedirectFixtures::cpUrl('redirects.reorder'), ['ids' => ['c', 'a', 'b']])
        ->assertRedirect(RedirectFixtures::cpUrl('redirects.index'))
        ->assertSessionHas('success', 'Redirects successfully reordered');

    expect(orders())->toBe(['a' => 2, 'b' => 3, 'c' => 1]);
});

it('numbers the redirects of a later page behind those of the pages before it', function (): void {
    $this->actingAs(RedirectFixtures::superUser())
        ->post(RedirectFixtures::cpUrl('redirects.reorder'), ['ids' => ['b', 'a'], 'page' => 3, 'perPage' => 2])
        ->assertRedirect();

    expect(orders())->toBe(['a' => 6, 'b' => 5, 'c' => 1]);
});

it('skips ids that are no redirect without leaving a gap', function (): void {
    $this->actingAs(RedirectFixtures::superUser())
        ->post(RedirectFixtures::cpUrl('redirects.reorder'), ['ids' => ['b', 'unknown', 'a']])
        ->assertRedirect();

    expect(orders())->toBe(['a' => 2, 'b' => 1, 'c' => 1]);
});

it('writes only the redirects whose order changes', function (): void {
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'a', site: 'de', source: '/a', destination: '/neu', order: 1));
    $redirects->save(new Redirect(id: 'b', site: 'de', source: '/b', destination: '/neu', order: 2));
    $redirects->save(new Redirect(id: 'c', site: 'de', source: '/c', destination: '/neu', order: 3));

    collect(['a', 'b', 'c'])->each(fn (string $id): bool => touch(new Redirect(id: $id, site: 'de')->path(), time() - 100));

    $this->actingAs(RedirectFixtures::superUser())
        ->post(RedirectFixtures::cpUrl('redirects.reorder'), ['ids' => ['a', 'c', 'b']])
        ->assertRedirect();

    clearstatcache();

    expect(orders())->toBe(['a' => 1, 'b' => 3, 'c' => 2])
        ->and(filemtime(new Redirect(id: 'a', site: 'de')->path()))->toBeLessThan(time() - 50)
        ->and(filemtime(new Redirect(id: 'b', site: 'de')->path()))->toBeGreaterThan(time() - 50)
        ->and(filemtime(new Redirect(id: 'c', site: 'de')->path()))->toBeGreaterThan(time() - 50);
});

it('refuses a user who may not edit redirects', function (): void {
    $this->actingAs(RedirectFixtures::userWith('view redirects'))
        ->postJson(RedirectFixtures::cpUrl('redirects.reorder'), ['ids' => ['c', 'a', 'b']])
        ->assertForbidden();

    expect(orders())->toBe(['a' => null, 'b' => 9, 'c' => 1]);
});

it('rejects :dataset', function (array $data): void {
    $this->actingAs(RedirectFixtures::superUser())
        ->post(RedirectFixtures::cpUrl('redirects.reorder'), $data)
        ->assertSessionHasErrors('ids');

    expect(orders())->toBe(['a' => null, 'b' => 9, 'c' => 1]);
})->with([
    'no ids' => [[]],
    'ids that are no list' => [['ids' => 'a']],
]);

it('rejects :dataset as page or page size', function (string $field, mixed $value): void {
    $this->actingAs(RedirectFixtures::superUser())
        ->post(RedirectFixtures::cpUrl('redirects.reorder'), ['ids' => ['a'], $field => $value])
        ->assertSessionHasErrors($field);

    expect(orders())->toBe(['a' => null, 'b' => 9, 'c' => 1]);
})->with([
    'page 0' => ['page', 0],
    'page size 0' => ['perPage', 0],
    'text' => ['page', 'two'],
]);

it('takes no page and no page size as the first page', function (): void {
    $this->actingAs(RedirectFixtures::superUser())
        ->post(RedirectFixtures::cpUrl('redirects.reorder'), ['ids' => ['b', 'a'], 'page' => null, 'perPage' => null])
        ->assertSessionHasNoErrors();

    expect(orders())->toBe(['a' => 2, 'b' => 1, 'c' => 1]);
});

it('takes a page size without a page as the first page', function (): void {
    $this->actingAs(RedirectFixtures::superUser())
        ->post(RedirectFixtures::cpUrl('redirects.reorder'), ['ids' => ['b', 'a'], 'perPage' => 2])
        ->assertSessionHasNoErrors();

    expect(orders())->toBe(['a' => 2, 'b' => 1, 'c' => 1]);
});

it('moves a redirect file with its order in front of the id', function (): void {
    $directory = RedirectFixtures::copyIntoStore();
    resolve(RedirectStore::class)->clear();
    $id = '5da06fa7-6162-4053-8fa6-8e7ae1bc2d06';

    $this->actingAs(RedirectFixtures::superUser())
        ->post(RedirectFixtures::cpUrl('redirects.reorder'), ['ids' => [$id]])
        ->assertRedirect();

    expect(File::exists("{$directory}en/2_{$id}.yaml"))->toBeFalse()
        ->and(File::exists("{$directory}en/{$id}.yaml"))->toBeTrue()
        ->and(resolve(RedirectRepository::class)->find($id)?->order())->toBe(1);
});
