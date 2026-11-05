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

/**
 * @param  array<mixed>  $query
 * @return array<mixed>
 */
function listedSources(array $query = []): array
{
    return test()->actingAs(RedirectFixtures::superUser())
        ->getJson(RedirectFixtures::cpUrl('redirects.index', $query))
        ->assertOk()
        ->collect('data.*.source')
        ->all();
}

function seedListing(): void
{
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'b', site: 'default', source: '/b', destination: '/neu', order: 2));
    $redirects->save(new Redirect(id: 'a', site: 'default', source: '/a', type: 410, matchType: 'regex', enabled: false, description: 'Alt', order: 3));
    $redirects->save(new Redirect(id: 'c', site: 'default', source: '/c', destination: '/zeit', type: 302, description: 'Kampagne', order: 1));
    $redirects->save(new Redirect(id: 'other-site', site: 'en', source: '/d', destination: '/new'));
}

it('opens the list with the filters and the urls of the listing', function (): void {
    seedListing();

    $this->actingAs(RedirectFixtures::superUser())
        ->get(RedirectFixtures::cpUrl('redirects.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('redirects::Index')
            ->where('hasRedirects', true)
            ->has('filters', 3)
            ->where('filters.0.handle', 'redirect_enabled')
            ->where('listingUrl', RedirectFixtures::cpUrl('redirects.index'))
            ->where('createUrl', RedirectFixtures::cpUrl('redirects.create'))
            ->where('importUrl', RedirectFixtures::cpUrl('redirects.import'))
            ->where('pruneUrl', RedirectFixtures::cpUrl('redirects.prune')));
});

it('tells the list when the selected site has no redirects', function (): void {
    seedListing();
    Site::setSites(['default' => ['url' => '/', 'locale' => 'en_US'], 'fr' => ['url' => '/fr/', 'locale' => 'fr_FR']]);

    $this->actingAs(RedirectFixtures::superUser())
        ->withSession(['statamic.cp.selected-site' => 'fr'])
        ->get(RedirectFixtures::cpUrl('redirects.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('hasRedirects', false));
});

it('lists the redirects of the selected site as JSON with their fields', function (): void {
    seedListing();

    $this->actingAs(RedirectFixtures::superUser())
        ->getJson(RedirectFixtures::cpUrl('redirects.index', ['sort' => 'order']))
        ->assertOk()
        ->assertJsonPath('data.0', [
            'id' => 'c',
            'enabled' => true,
            'source' => '/c',
            'destination' => '/zeit',
            'type' => 302,
            'match_type' => 'exact',
            'description' => 'Kampagne',
            'order' => 1,
            'edit_url' => RedirectFixtures::cpUrl('redirects.edit', 'c'),
        ])
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.activeFilterBadges', [])
        ->assertJsonPath('meta.columns', [
            ['field' => 'source', 'label' => 'Source', 'visible' => true, 'sortable' => true],
            ['field' => 'destination', 'label' => 'Destination', 'visible' => true, 'sortable' => true],
            ['field' => 'type', 'label' => 'Type', 'visible' => true, 'sortable' => true],
            ['field' => 'match_type', 'label' => 'Match type', 'visible' => true, 'sortable' => true],
        ]);
});

it('sorts the list by source unless asked otherwise', function (array $query, array $expected): void {
    seedListing();

    expect(listedSources($query))->toBe($expected);
})->with([
    'default' => [[], ['/a', '/b', '/c']],
    'by order' => [['sort' => 'order'], ['/c', '/b', '/a']],
    'descending' => [['sort' => 'source', 'order' => 'desc'], ['/c', '/b', '/a']],
    'by type' => [['sort' => 'type', 'order' => 'desc'], ['/a', '/c', '/b']],
    'by destination' => [['sort' => 'destination'], ['/b', '/c', '/a']],
    'by match type' => [['sort' => 'match_type', 'order' => 'desc'], ['/a', '/b', '/c']],
]);

it('searches the source, destination and description', function (string $search, array $expected): void {
    seedListing();

    expect(listedSources(['search' => $search]))->toBe($expected);
})->with([
    'source' => ['/b', ['/b']],
    'destination' => ['zeit', ['/c']],
    'description' => ['kampagne', ['/c']],
    'nothing' => ['gibt-es-nicht', []],
    'empty' => ['', ['/a', '/b', '/c']],
]);

it('filters the list', function (array $filters, array $expected, array $badges): void {
    seedListing();

    $response = $this->actingAs(RedirectFixtures::superUser())
        ->getJson(RedirectFixtures::cpUrl('redirects.index', ['filters' => base64_encode(json_encode($filters, JSON_THROW_ON_ERROR))]))
        ->assertOk();

    expect($response->json('data.*.source'))->toBe($expected)
        ->and($response->json('meta.activeFilterBadges'))->toBe($badges);
})->with([
    'enabled' => [['redirect_enabled' => ['enabled' => '1']], ['/b', '/c'], ['redirect_enabled' => 'Enabled']],
    'disabled' => [['redirect_enabled' => ['enabled' => '0']], ['/a'], ['redirect_enabled' => 'Disabled']],
    'regex' => [['redirect_match_type' => ['match_type' => 'regex']], ['/a'], ['redirect_match_type' => 'Regex match']],
    'exact' => [['redirect_match_type' => ['match_type' => 'exact']], ['/b', '/c'], ['redirect_match_type' => 'Exact match']],
    'type' => [['redirect_type' => ['type' => '302']], ['/c'], ['redirect_type' => 'Temporary (302)']],
    'several' => [['redirect_enabled' => ['enabled' => '1'], 'redirect_type' => ['type' => '301']], ['/b'], ['redirect_enabled' => 'Enabled', 'redirect_type' => 'Permanent (301)']],
]);

it('pages the list', function (): void {
    seedListing();

    $response = $this->actingAs(RedirectFixtures::superUser())
        ->getJson(RedirectFixtures::cpUrl('redirects.index', ['perPage' => 2, 'page' => 2]))
        ->assertOk();

    expect($response->json('data.*.source'))->toBe(['/c'])
        ->and($response->json('meta.last_page'))->toBe(2);
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

it('stores a redirect in the selected site as a file', function (): void {
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
        order: 1

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

it('updates a redirect file with its order in front of the id into one file with its id, site and order', function (): void {
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
