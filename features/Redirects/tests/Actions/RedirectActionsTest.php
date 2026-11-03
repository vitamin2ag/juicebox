<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Statamic\Actions\Action as StatamicAction;
use Statamic\Facades\Action;
use Vitamin2\Juicebox\Features\Redirects\Actions\DeleteRedirects;
use Vitamin2\Juicebox\Features\Redirects\Actions\DisableRedirects;
use Vitamin2\Juicebox\Features\Redirects\Actions\EnableRedirects;
use Vitamin2\Juicebox\Features\Redirects\Actions\RedirectAction;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;
use Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__\RedirectFixtures;

mutates(RedirectAction::class, DeleteRedirects::class, EnableRedirects::class, DisableRedirects::class);

beforeEach(function (): void {
    RedirectFixtures::isolate();
});

/**
 * @return Collection<int, Redirect>
 */
function storedRedirects(): Collection
{
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'on', site: 'de', source: '/an', destination: '/neu'));
    $redirects->save(new Redirect(id: 'off', site: 'de', source: '/aus', destination: '/neu', enabled: false));

    return $redirects->all()->sortBy(fn (Redirect $redirect): string => $redirect->id())->values();
}

it('offers its actions on redirects and nothing else', function (): void {
    $this->actingAs(RedirectFixtures::superUser());
    $classes = fn (mixed $item): array => Collection::wrap(Action::for($item, ['view' => 'list']))
        ->whereInstanceOf(StatamicAction::class)
        ->filter(fn (StatamicAction $action): bool => $action instanceof RedirectAction)
        ->map(fn (StatamicAction $action): string => $action::class)
        ->sort()
        ->values()
        ->all();

    expect($classes(new Redirect(id: 'one', site: 'de')))->toBe([DeleteRedirects::class, DisableRedirects::class, EnableRedirects::class])
        ->and($classes(RedirectFixtures::page('seite')))->toBeEmpty();
});

it('lets the permission decide who may run :dataset', function (RedirectAction $action, string $permission): void {
    $redirect = new Redirect(id: 'one', site: 'de');

    expect($action->authorize(RedirectFixtures::userWith($permission), $redirect))->toBeTrue()
        ->and($action->authorize(RedirectFixtures::userWith('view redirects'), $redirect))->toBeFalse()
        ->and($action->authorize(null, $redirect))->toBeFalse();
})->with([
    'delete' => [fn (): RedirectAction => new DeleteRedirects, 'delete redirects'],
    'enable' => [fn (): RedirectAction => new EnableRedirects, 'edit redirects'],
    'disable' => [fn (): RedirectAction => new DisableRedirects, 'edit redirects'],
]);

it('deletes the redirects and removes them from the selection', function (): void {
    $result = new DeleteRedirects()->run(storedRedirects(), []);

    expect(resolve(RedirectRepository::class)->all())->toBeEmpty()
        ->and($result['message'])->toBe('Items deleted')
        ->and($result['callback'][0])->toBe('removeFromSelections')
        ->and($result['callback'][1]->all())->toBe(['off', 'on']);
});

it('enables the redirects', function (): void {
    $message = new EnableRedirects()->run(storedRedirects(), []);

    expect(resolve(RedirectRepository::class)->whereEnabled(false))->toBeEmpty()
        ->and($message)->toBe('Redirects enabled');
});

it('disables the redirects', function (): void {
    $message = new DisableRedirects()->run(storedRedirects(), []);

    expect(resolve(RedirectRepository::class)->whereEnabled(true))->toBeEmpty()
        ->and($message)->toBe('Redirects disabled');
});

it('ignores what is not a redirect', function (): void {
    $redirects = storedRedirects();

    new DeleteRedirects()->run(collect(['on', null, RedirectFixtures::page('seite')]), []);

    expect(resolve(RedirectRepository::class)->all()->count())->toBe($redirects->count());
});

it('warns before it deletes', function (): void {
    expect(new DeleteRedirects()->toArray())->toHaveKey('dangerous', true)
        ->and(new EnableRedirects()->toArray())->toHaveKey('dangerous', false)
        ->and(new DisableRedirects()->toArray())->toHaveKey('dangerous', false);
});
