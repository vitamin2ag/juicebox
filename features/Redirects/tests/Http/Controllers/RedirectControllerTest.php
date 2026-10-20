<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use Statamic\Facades\Site;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Http\Controllers\RedirectController;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectStore;
use Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__\RedirectFixtures;

mutates(RedirectController::class);

beforeEach(function (): void {
    RedirectFixtures::isolate();
});

/**
 * @param  array<string, mixed>  $values
 * @return array<string, mixed>
 */
function redirectForm(array $values = []): array
{
    return [
        'match_type' => 'exact',
        'source' => '/alt',
        'destination' => '/neu',
        'type' => 301,
        'description' => null,
        'enabled' => true,
        ...$values,
    ];
}

it('lists the redirects of the selected site by source', function (): void {
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'b', site: 'default', source: '/b', destination: '/neu'));
    $redirects->save(new Redirect(id: 'a', site: 'default', source: '/a', type: 410, matchType: 'regex', enabled: false, description: 'Alt', order: 3));
    $redirects->save(new Redirect(id: 'other-site', site: 'en', source: '/c', destination: '/new'));

    $this->actingAs(RedirectFixtures::superUser())
        ->get(RedirectFixtures::cpUrl('redirects.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('redirects::Index')
            ->where('redirects', [
                [
                    'id' => 'a',
                    'enabled' => false,
                    'source' => '/a',
                    'destination' => null,
                    'type' => 410,
                    'match_type' => 'regex',
                    'description' => 'Alt',
                    'order' => 3,
                    'edit_url' => RedirectFixtures::cpUrl('redirects.edit', 'a'),
                    'delete_url' => RedirectFixtures::cpUrl('redirects.destroy', 'a'),
                ],
                [
                    'id' => 'b',
                    'enabled' => true,
                    'source' => '/b',
                    'destination' => '/neu',
                    'type' => 301,
                    'match_type' => 'exact',
                    'description' => null,
                    'order' => null,
                    'edit_url' => RedirectFixtures::cpUrl('redirects.edit', 'b'),
                    'delete_url' => RedirectFixtures::cpUrl('redirects.destroy', 'b'),
                ],
            ])
            ->where('createUrl', RedirectFixtures::cpUrl('redirects.create'))
            ->where('importUrl', RedirectFixtures::cpUrl('redirects.import'))
            ->where('pruneUrl', RedirectFixtures::cpUrl('redirects.prune')));
});

it('shows the form to create a redirect with the blueprint defaults', function (): void {
    $this->actingAs(RedirectFixtures::superUser())
        ->get(RedirectFixtures::cpUrl('redirects.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('PublishForm')
            ->where('title', 'Create Redirect')
            ->where('icon', 'arrow-up-right')
            ->where('blueprint.handle', 'redirect')
            ->where('values.match_type', 'exact')
            ->where('values.source', null)
            ->where('values.type', 301)
            ->where('values.enabled', true)
            ->has('meta.destination')
            ->where('submitUrl', RedirectFixtures::cpUrl('redirects.store'))
            ->where('submitMethod', 'POST'));
});

it('stores a redirect in the selected site in the format of rias', function (): void {
    config(['statamic.editions.pro' => true, 'statamic.system.multisite' => true]);
    Site::setSites([
        'default' => ['name' => 'Deutsch', 'url' => '/', 'locale' => 'de'],
        'en' => ['name' => 'English', 'url' => '/en/', 'locale' => 'en'],
    ]);

    $response = $this->actingAs(RedirectFixtures::superUser())
        ->withSession(['statamic.cp.selected-site' => 'en'])
        ->postJson(RedirectFixtures::cpUrl('redirects.store'), redirectForm(['description' => 'Alte Seite']))
        ->assertOk();

    $redirect = resolve(RedirectRepository::class)->all()->sole();

    $response->assertExactJson(['redirect' => RedirectFixtures::cpUrl('redirects.edit', $redirect->id())]);
    expect(File::get(resolve(RedirectStore::class)->directory()."en/{$redirect->id()}.yaml"))->toBe(<<<YAML
        id: {$redirect->id()}
        enabled: true
        source: /alt
        destination: /neu
        type: 301
        match_type: exact
        description: 'Alte Seite'

        YAML);
});

it('stores a 410 redirect without a destination', function (): void {
    $this->actingAs(RedirectFixtures::superUser())
        ->postJson(RedirectFixtures::cpUrl('redirects.store'), redirectForm(['destination' => null, 'type' => '410', 'enabled' => false]))
        ->assertOk();

    $redirect = resolve(RedirectRepository::class)->all()->sole();

    expect($redirect->type())->toBe(410)
        ->and($redirect->destination())->toBeNull()
        ->and($redirect->enabled())->toBeFalse();
});

it('requires a destination for a :dataset redirect', function (int $type): void {
    $this->actingAs(RedirectFixtures::superUser())
        ->postJson(RedirectFixtures::cpUrl('redirects.store'), redirectForm(['destination' => null, 'type' => $type]))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors('destination');

    expect(resolve(RedirectRepository::class)->all())->toBeEmpty();
})->with([301, 302]);

it('rejects a source that already redirects in the selected site', function (): void {
    resolve(RedirectRepository::class)->save(new Redirect(id: 'taken', site: 'default', source: '/alt', destination: '/anders'));

    $this->actingAs(RedirectFixtures::superUser())
        ->postJson(RedirectFixtures::cpUrl('redirects.store'), redirectForm())
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors('source');

    expect(resolve(RedirectRepository::class)->all())->toHaveCount(1);
});

it('rejects a regex source that does not compile', function (): void {
    $this->actingAs(RedirectFixtures::superUser())
        ->postJson(RedirectFixtures::cpUrl('redirects.store'), redirectForm(['source' => '/kampagne/(', 'match_type' => 'regex']))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors('source');

    expect(resolve(RedirectRepository::class)->all())->toBeEmpty();
});

it('shows the form to edit a redirect with its values', function (): void {
    resolve(RedirectRepository::class)->save(new Redirect(id: 'edited', site: 'default', source: '/alt', destination: '/neu', type: 302, matchType: 'regex', description: 'Alt'));

    $this->actingAs(RedirectFixtures::superUser())
        ->get(RedirectFixtures::cpUrl('redirects.edit', 'edited'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('PublishForm')
            ->where('title', 'Edit Redirect')
            ->where('icon', 'arrow-up-right')
            ->where('blueprint.handle', 'redirect')
            ->where('values.source', '/alt')
            ->where('values.destination', '/neu')
            ->where('values.type', 302)
            ->where('values.match_type', 'regex')
            ->where('values.description', 'Alt')
            ->where('values.enabled', true)
            ->has('meta.destination')
            ->where('submitUrl', RedirectFixtures::cpUrl('redirects.update', 'edited'))
            ->where('submitMethod', 'PATCH'));
});

it('updates a rias redirect into one file with its id, site and order', function (): void {
    $directory = RedirectFixtures::copyIntoStore();

    $this->actingAs(RedirectFixtures::superUser())
        ->patchJson(RedirectFixtures::cpUrl('redirects.update', '5da06fa7-6162-4053-8fa6-8e7ae1bc2d06'), redirectForm(['source' => '/kampagne/(.*)', 'match_type' => 'regex', 'destination' => '/aktion/$1']))
        ->assertNoContent();

    expect($directory.'en/2_5da06fa7-6162-4053-8fa6-8e7ae1bc2d06.yaml')->not->toBeFile()
        ->and(File::get($directory.'en/5da06fa7-6162-4053-8fa6-8e7ae1bc2d06.yaml'))->toBe(<<<'YAML'
            id: 5da06fa7-6162-4053-8fa6-8e7ae1bc2d06
            enabled: true
            source: '/kampagne/(.*)'
            destination: /aktion/$1
            type: 301
            match_type: regex
            order: 2

            YAML);
});

it('validates an update like a new redirect', function (): void {
    resolve(RedirectRepository::class)->save(new Redirect(id: 'edited', site: 'default', source: '/alt', destination: '/neu'));
    resolve(RedirectRepository::class)->save(new Redirect(id: 'taken', site: 'default', source: '/taken', destination: '/neu'));

    $this->actingAs(RedirectFixtures::superUser())
        ->patchJson(RedirectFixtures::cpUrl('redirects.update', 'edited'), redirectForm(['source' => '/taken', 'destination' => null]))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors(['source', 'destination']);

    expect(resolve(RedirectRepository::class)->find('edited')?->source())->toBe('/alt');
});

it('deletes a redirect', function (): void {
    resolve(RedirectRepository::class)->save(new Redirect(id: 'deleted', site: 'default', source: '/alt', destination: '/neu'));

    $this->actingAs(RedirectFixtures::superUser())
        ->deleteJson(RedirectFixtures::cpUrl('redirects.destroy', 'deleted'))
        ->assertNoContent();

    expect(resolve(RedirectRepository::class)->all())->toBeEmpty();
});

it('answers :dataset for an unknown redirect with 404', function (string $method, string $route): void {
    $this->actingAs(RedirectFixtures::superUser())
        ->json($method, RedirectFixtures::cpUrl($route, 'unknown'), redirectForm())
        ->assertNotFound();
})->with([
    'edit' => ['get', 'redirects.edit'],
    'update' => ['patch', 'redirects.update'],
    'destroy' => ['delete', 'redirects.destroy'],
]);

dataset('redirect actions', [
    'index' => ['get', 'redirects.index', 'view redirects', 200],
    'create' => ['get', 'redirects.create', 'create redirects', 200],
    'store' => ['post', 'redirects.store', 'create redirects', 422],
    'edit' => ['get', 'redirects.edit', 'edit redirects', 200],
    'update' => ['patch', 'redirects.update', 'edit redirects', 422],
    'destroy' => ['delete', 'redirects.destroy', 'delete redirects', 204],
]);

it('allows :dataset with its permission', function (string $method, string $route, string $permission, int $status): void {
    resolve(RedirectRepository::class)->save(new Redirect(id: 'existing', site: 'default', source: '/alt', destination: '/neu'));

    $this->actingAs(RedirectFixtures::userWith($permission))
        ->json($method, RedirectFixtures::cpUrl($route, ['id' => 'existing']))
        ->assertStatus($status);
})->with('redirect actions');

it('forbids :dataset without its permission', function (string $method, string $route, string $permission): void {
    resolve(RedirectRepository::class)->save(new Redirect(id: 'existing', site: 'default', source: '/alt', destination: '/neu'));
    $others = array_values(array_diff(['view redirects', 'edit redirects', 'create redirects', 'delete redirects'], [$permission]));

    $this->actingAs(RedirectFixtures::userWith(...$others))
        ->json($method, RedirectFixtures::cpUrl($route, ['id' => 'existing']))
        ->assertForbidden();

    expect(resolve(RedirectRepository::class)->find('existing'))->not->toBeNull();
})->with('redirect actions');
