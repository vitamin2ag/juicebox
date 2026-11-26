<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Statamic\Facades\Action;
use Vitamin2\Juicebox\Features\Shortlinks\Actions\DeleteShortlinks;
use Vitamin2\Juicebox\Features\Shortlinks\Data\Shortlink;
use Vitamin2\Juicebox\Features\Shortlinks\Stache\ShortlinkRepository;
use Vitamin2\Juicebox\Features\Shortlinks\Tests\__Fixtures__\ShortlinkFixtures;

covers(DeleteShortlinks::class);

beforeAll(function (): void {
    ShortlinkFixtures::enable();
});

afterAll(function (): void {
    ShortlinkFixtures::disable();
});

beforeEach(function (): void {
    ShortlinkFixtures::isolate();
});

/**
 * @return Collection<int, Shortlink>
 */
function storedShortlinks(): Collection
{
    $shortlinks = resolve(ShortlinkRepository::class);
    $shortlinks->save(new Shortlink(slug: 'sale', destination: '/angebot'));
    $shortlinks->save(new Shortlink(slug: 'team', destination: '/team', enabled: false));

    return $shortlinks->all()->sortBy(fn (Shortlink $shortlink): string => $shortlink->slug())->values();
}

it('offers itself on shortlinks and nothing else', function (): void {
    $this->actingAs(ShortlinkFixtures::superUser());
    $offered = fn (mixed $item): bool => Collection::wrap(Action::for($item, ['view' => 'list']))
        ->contains(fn (mixed $action): bool => $action instanceof DeleteShortlinks);

    expect($offered(new Shortlink(slug: 'sale')))->toBeTrue()
        ->and($offered(ShortlinkFixtures::page('team')))->toBeFalse();
});

it('lets the delete permission decide who may run it', function (): void {
    $shortlink = new Shortlink(slug: 'sale');

    expect(new DeleteShortlinks()->authorize(ShortlinkFixtures::userWith('delete shortlinks'), $shortlink))->toBeTrue()
        ->and(new DeleteShortlinks()->authorize(ShortlinkFixtures::userWith('view shortlinks', 'edit shortlinks'), $shortlink))->toBeFalse()
        ->and(new DeleteShortlinks()->authorize(null, $shortlink))->toBeFalse();
});

it('asks before deleting shortlinks', function (): void {
    expect(new DeleteShortlinks()->toArray())
        ->toHaveKey('handle', 'delete_shortlinks')
        ->toHaveKey('title', 'Delete')
        ->toHaveKey('icon', 'trash')
        ->toHaveKey('dangerous', true);
});

it('deletes the shortlinks and removes them from the selection', function (): void {
    $result = new DeleteShortlinks()->run(storedShortlinks(), []);

    expect(resolve(ShortlinkRepository::class)->all())->toBeEmpty()
        ->and($result['message'])->toBe('Items deleted')
        ->and($result['callback'][0])->toBe('removeFromSelections')
        ->and($result['callback'][1]->all())->toBe(['sale', 'team']);
});

it('ignores what is not a shortlink', function (): void {
    $shortlinks = storedShortlinks();

    $result = new DeleteShortlinks()->run(collect(['sale', null, ShortlinkFixtures::page('team')]), []);

    expect(resolve(ShortlinkRepository::class)->all()->count())->toBe($shortlinks->count())
        ->and($result['callback'][1]->all())->toBeEmpty();
});
