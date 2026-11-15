<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use Statamic\Auth\File\User;
use Vitamin2\Juicebox\Features\Shortlinks\Data\Shortlink;
use Vitamin2\Juicebox\Features\Shortlinks\Http\Controllers\ShortlinkController;
use Vitamin2\Juicebox\Features\Shortlinks\Http\EntryForm;
use Vitamin2\Juicebox\Features\Shortlinks\Http\Resources\ShortlinkResource;
use Vitamin2\Juicebox\Features\Shortlinks\Qr\QrCode;
use Vitamin2\Juicebox\Features\Shortlinks\Rules\UniqueSlug;
use Vitamin2\Juicebox\Features\Shortlinks\Rules\ValidDestination;
use Vitamin2\Juicebox\Features\Shortlinks\Stache\ShortlinkRepository;
use Vitamin2\Juicebox\Features\Shortlinks\Stache\ShortlinkStore;
use Vitamin2\Juicebox\Features\Shortlinks\Tests\__Fixtures__\ShortlinkFixtures;

covers(ShortlinkController::class, ShortlinkResource::class, EntryForm::class, UniqueSlug::class, ValidDestination::class);

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
 * @param  array<string, mixed>  $values
 * @return array<string, mixed>
 */
function shortlinkForm(array $values = []): array
{
    return [
        'destination' => 'https://www.example.com/angebot',
        'slug' => 'sale',
        'description' => null,
        'utm' => false,
        'utm_source' => null,
        'utm_medium' => null,
        'utm_campaign' => null,
        'enabled' => true,
        ...$values,
    ];
}

/**
 * @param  array<mixed>  $query
 * @return array<mixed>
 */
function listedSlugs(array $query = []): array
{
    return test()->actingAs(ShortlinkFixtures::superUser())
        ->getJson(ShortlinkFixtures::cpUrl('shortlinks.index', $query))
        ->assertOk()
        ->collect('data.*.slug')
        ->all();
}

function seedShortlinks(): void
{
    $shortlinks = resolve(ShortlinkRepository::class);
    $shortlinks->save(new Shortlink(slug: 'team', destination: '/kontakt', description: 'Flyer', enabled: false));
    $shortlinks->save(new Shortlink(slug: 'sale', destination: 'https://www.example.com/angebot', description: 'Kampagne'));
    $shortlinks->save(new Shortlink(slug: 'event', destination: '/zeit', description: 'Abend'));
}

it('opens the list with the urls of the listing', function (): void {
    seedShortlinks();

    $this->actingAs(ShortlinkFixtures::superUser())
        ->get(ShortlinkFixtures::cpUrl('shortlinks.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('shortlinks::Index')
            ->where('hasShortlinks', true)
            ->where('listingUrl', ShortlinkFixtures::cpUrl('shortlinks.index'))
            ->where('createUrl', ShortlinkFixtures::cpUrl('shortlinks.create'))
            ->where('actionUrl', ShortlinkFixtures::cpUrl('shortlinks.actions.run'))
            ->where('entryForm.formComponent', 'entry-publish-form')
            ->where('entryForm.formComponentProps.initialValues', 'values')
            ->where('entryForm.formStackSize', null)
            ->has('entryForm', 3));
});

it('tells the list when there are no shortlinks', function (): void {
    $this->actingAs(ShortlinkFixtures::superUser())
        ->get(ShortlinkFixtures::cpUrl('shortlinks.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('hasShortlinks', false));
});

it('lists the shortlinks as JSON with their fields and links', function (): void {
    resolve(ShortlinkRepository::class)->save(new Shortlink(
        slug: 'sale',
        destination: 'https://www.example.com/angebot',
        description: 'Flyer',
        utm: true,
        utmSource: 'flyer',
        utmMedium: 'print',
        utmCampaign: 'autumn-sale',
    ));

    $this->actingAs(ShortlinkFixtures::superUser())
        ->getJson(ShortlinkFixtures::cpUrl('shortlinks.index'))
        ->assertOk()
        ->assertJsonPath('data.0', [
            'id' => 'sale',
            'slug' => 'sale',
            'destination' => 'https://www.example.com/angebot',
            'description' => 'Flyer',
            'utm' => true,
            'utm_source' => 'flyer',
            'utm_medium' => 'print',
            'utm_campaign' => 'autumn-sale',
            'enabled' => true,
            'url' => url('s/sale'),
            'edit_url' => ShortlinkFixtures::cpUrl('shortlinks.edit', 'sale'),
            'qr_url' => ShortlinkFixtures::cpUrl('shortlinks.qr', 'sale'),
            'destination_entry' => null,
        ])
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.columns', [
            ['field' => 'slug', 'label' => 'Slug', 'visible' => true, 'sortable' => true],
            ['field' => 'destination', 'label' => 'Destination', 'visible' => true, 'sortable' => true],
            ['field' => 'description', 'label' => 'Description', 'visible' => true, 'sortable' => true],
        ]);
});

it('shows the title, link and status of the entry a destination points to', function (): void {
    $entry = ShortlinkFixtures::page('team');
    $entry->set('title', 'Team');
    $entry->save();
    resolve(ShortlinkRepository::class)->save(new Shortlink(slug: 'team', destination: 'entry::team'));

    $this->actingAs(ShortlinkFixtures::superUser())
        ->getJson(ShortlinkFixtures::cpUrl('shortlinks.index'))
        ->assertJsonPath('data.0.destination_entry', ['title' => 'Team', 'edit_url' => $entry->editUrl(), 'status' => 'published']);
});

it('shows the status of a draft entry a destination points to', function (): void {
    ShortlinkFixtures::page('team', published: false);
    resolve(ShortlinkRepository::class)->save(new Shortlink(slug: 'team', destination: 'entry::team'));

    $this->actingAs(ShortlinkFixtures::superUser())
        ->getJson(ShortlinkFixtures::cpUrl('shortlinks.index'))
        ->assertJsonPath('data.0.destination_entry.status', 'draft');
});

it('titles an entry without a title by its destination', function (): void {
    ShortlinkFixtures::page('team');
    resolve(ShortlinkRepository::class)->save(new Shortlink(slug: 'team', destination: 'entry::team'));

    $this->actingAs(ShortlinkFixtures::superUser())
        ->getJson(ShortlinkFixtures::cpUrl('shortlinks.index'))
        ->assertJsonPath('data.0.destination_entry.title', 'entry::team');
});

it('has no entry for :dataset', function (?string $destination): void {
    ShortlinkFixtures::page('team');
    resolve(ShortlinkRepository::class)->save(new Shortlink(slug: 'team', destination: $destination));

    $this->actingAs(ShortlinkFixtures::superUser())
        ->getJson(ShortlinkFixtures::cpUrl('shortlinks.index'))
        ->assertJsonPath('data.0.destination_entry', null);
})->with([
    'a missing entry' => 'entry::missing',
    'a url' => '/team',
    'no destination' => null,
]);

it('sorts the list by slug unless asked otherwise', function (array $query, array $expected): void {
    seedShortlinks();

    expect(listedSlugs($query))->toBe($expected);
})->with([
    'default' => [[], ['event', 'sale', 'team']],
    'descending' => [['order' => 'desc'], ['team', 'sale', 'event']],
    'by destination' => [['sort' => 'destination'], ['team', 'event', 'sale']],
    'by description' => [['sort' => 'description'], ['event', 'team', 'sale']],
]);

it('searches the slug, destination and description', function (string $search, array $expected): void {
    seedShortlinks();

    expect(listedSlugs(['search' => $search]))->toBe($expected);
})->with([
    'slug' => ['ven', ['event']],
    'destination' => ['example', ['sale']],
    'description' => ['flyer', ['team']],
    'nothing' => ['gibt-es-nicht', []],
    'empty' => ['', ['event', 'sale', 'team']],
]);

it('pages the list', function (): void {
    seedShortlinks();

    $response = $this->actingAs(ShortlinkFixtures::superUser())
        ->getJson(ShortlinkFixtures::cpUrl('shortlinks.index', ['perPage' => 2, 'page' => 2]))
        ->assertOk();

    expect($response->json('data.*.slug'))->toBe(['team'])
        ->and($response->json('meta.last_page'))->toBe(2);
});

it('shows the form to create a shortlink with the blueprint defaults and an editable slug', function (): void {
    $this->actingAs(ShortlinkFixtures::superUser())
        ->get(ShortlinkFixtures::cpUrl('shortlinks.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('PublishForm')
            ->where('title', 'Create Shortlink')
            ->where('icon', 'link')
            ->where('blueprint.handle', 'shortlink')
            ->where('blueprint.tabs.0.sections.0.fields.1.handle', 'slug')
            ->where('blueprint.tabs.0.sections.0.fields.1.visibility', 'visible')
            ->where('blueprint.tabs.1.sections.0.fields.1.handle', 'qr_code')
            ->where('blueprint.tabs.1.sections.0.fields.1.html', '<p>The QR code appears after saving.</p>')
            ->where('values.slug', null)
            ->where('values.utm', false)
            ->where('values.enabled', true)
            ->has('meta.destination')
            ->where('submitUrl', ShortlinkFixtures::cpUrl('shortlinks.store'))
            ->where('submitMethod', 'POST'));
});

it('stores a shortlink as a file named by its slug', function (): void {
    $this->actingAs(ShortlinkFixtures::superUser())
        ->postJson(ShortlinkFixtures::cpUrl('shortlinks.store'), shortlinkForm([
            'description' => 'Flyer',
            'utm' => true,
            'utm_source' => 'flyer',
            'utm_medium' => 'print',
            'utm_campaign' => 'autumn-sale',
        ]))
        ->assertOk()
        ->assertExactJson(['redirect' => ShortlinkFixtures::cpUrl('shortlinks.edit', 'sale')]);

    expect(File::get(resolve(ShortlinkStore::class)->directory().'sale.yaml'))->toBe(<<<'YAML'
        destination: 'https://www.example.com/angebot'
        description: Flyer
        utm: true
        utm_source: flyer
        utm_medium: print
        utm_campaign: autumn-sale
        enabled: true

        YAML);
});

it('stores a disabled shortlink to an entry without UTM parameters while UTM is off', function (): void {
    $this->actingAs(ShortlinkFixtures::superUser())
        ->postJson(ShortlinkFixtures::cpUrl('shortlinks.store'), shortlinkForm(['destination' => 'entry::team', 'enabled' => false]))
        ->assertOk();

    expect(File::get(resolve(ShortlinkStore::class)->directory().'sale.yaml'))->toBe(<<<'YAML'
        destination: 'entry::team'
        utm: false
        enabled: false

        YAML);
});

it('rejects :dataset', function (string $field, mixed $value, array $errors): void {
    $this->actingAs(ShortlinkFixtures::superUser())
        ->postJson(ShortlinkFixtures::cpUrl('shortlinks.store'), shortlinkForm([$field => $value]))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors($errors);

    expect(resolve(ShortlinkRepository::class)->all())->toBeEmpty();
})->with([
    'a missing slug' => ['slug', null, ['slug']],
    'a slug with upper case letters' => ['slug', 'Sale', ['slug']],
    'a slug with a space' => ['slug', 'autumn sale', ['slug']],
    'a slug that ends with a hyphen' => ['slug', 'sale-', ['slug']],
    'a missing destination' => ['destination', null, ['destination']],
    'a destination without a scheme' => ['destination', 'www.example.com', ['destination']],
    'UTM without its parameters' => ['utm', true, ['utm_source', 'utm_medium', 'utm_campaign']],
]);

it('rejects the slug of another shortlink', function (): void {
    resolve(ShortlinkRepository::class)->save(new Shortlink(slug: 'sale', destination: '/kontakt'));

    $this->actingAs(ShortlinkFixtures::superUser())
        ->postJson(ShortlinkFixtures::cpUrl('shortlinks.store'), shortlinkForm())
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors('slug');

    expect(resolve(ShortlinkRepository::class)->find('sale')?->destination())->toBe('/kontakt');
});

it('shows the form to edit a shortlink with its values, a read-only slug and its QR code', function (): void {
    resolve(ShortlinkRepository::class)->save(new Shortlink(slug: 'sale', destination: '/kontakt', description: 'Flyer', enabled: false));
    $svg = base64_encode(new QrCode()->svg(url('s/sale')));
    $download = ShortlinkFixtures::cpUrl('shortlinks.qr', 'sale');

    $this->actingAs(ShortlinkFixtures::superUser())
        ->get(ShortlinkFixtures::cpUrl('shortlinks.edit', 'sale'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('PublishForm')
            ->where('title', 'Edit Shortlink')
            ->where('icon', 'link')
            ->where('blueprint.handle', 'shortlink')
            ->where('blueprint.tabs.0.sections.0.fields.1.handle', 'slug')
            ->where('blueprint.tabs.0.sections.0.fields.1.visibility', 'read_only')
            ->where('blueprint.tabs.1.sections.0.fields.1.handle', 'qr_code')
            ->where('blueprint.tabs.1.sections.0.fields.1.html', '<img src="data:image/svg+xml;base64,'.$svg.'" alt="QR code of '.url('s/sale').'"><p><a href="'.$download.'" class="underline">Download QR code</a></p>')
            ->where('values.slug', 'sale')
            ->where('values.destination', '/kontakt')
            ->where('values.description', 'Flyer')
            ->where('values.utm', false)
            ->where('values.enabled', false)
            ->has('meta.destination')
            ->where('submitUrl', ShortlinkFixtures::cpUrl('shortlinks.update', 'sale'))
            ->where('submitMethod', 'PATCH'));
});

it('shows the forms in :dataset, the language of the user', function (string $locale): void {
    resolve(ShortlinkRepository::class)->save(new Shortlink(slug: 'sale', destination: '/kontakt'));
    $user = Collection::wrap(ShortlinkFixtures::superUser())
        ->whereInstanceOf(User::class)
        ->firstOrFail();
    $user->setPreference('locale', $locale);
    $user->save();

    $this->actingAs($user)
        ->get(ShortlinkFixtures::cpUrl('shortlinks.create'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('title', 'Shortlink erstellen')
            ->where('blueprint.tabs.1.sections.0.fields.1.html', '<p>Der QR-Code erscheint nach dem Speichern.</p>'));

    $this->get(ShortlinkFixtures::cpUrl('shortlinks.edit', 'sale'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('title', 'Shortlink bearbeiten')
            ->where('blueprint.tabs.1.sections.0.fields.1.html', fn (string $html): bool => str_contains($html, 'alt="QR-Code von '.url('s/sale').'"')
                && str_contains($html, '>QR-Code herunterladen</a>')));
})->with(['German' => 'de', 'Swiss German' => 'de_CH']);

it('updates a shortlink and keeps its slug', function (): void {
    resolve(ShortlinkRepository::class)->save(new Shortlink(slug: 'sale', destination: '/kontakt'));

    $this->actingAs(ShortlinkFixtures::superUser())
        ->patchJson(ShortlinkFixtures::cpUrl('shortlinks.update', 'sale'), shortlinkForm(['slug' => 'Other slug', 'destination' => 'entry::team']))
        ->assertNoContent();

    $shortlinks = resolve(ShortlinkRepository::class)->all()
        ->map(fn (Shortlink $shortlink): array => [$shortlink->slug(), $shortlink->destination()])
        ->all();

    expect($shortlinks)->toBe([['sale', 'entry::team']]);
});

it('validates an update like a new shortlink', function (): void {
    resolve(ShortlinkRepository::class)->save(new Shortlink(slug: 'sale', destination: '/kontakt'));

    $this->actingAs(ShortlinkFixtures::superUser())
        ->patchJson(ShortlinkFixtures::cpUrl('shortlinks.update', 'sale'), shortlinkForm(['destination' => 'www.example.com', 'utm' => true]))
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors(['destination', 'utm_source', 'utm_medium', 'utm_campaign']);

    expect(resolve(ShortlinkRepository::class)->find('sale')?->destination())->toBe('/kontakt');
});

it('deletes a shortlink', function (): void {
    resolve(ShortlinkRepository::class)->save(new Shortlink(slug: 'sale', destination: '/kontakt'));

    $this->actingAs(ShortlinkFixtures::superUser())
        ->deleteJson(ShortlinkFixtures::cpUrl('shortlinks.destroy', 'sale'))
        ->assertNoContent();

    expect(resolve(ShortlinkRepository::class)->all())->toBeEmpty();
});

it('downloads the QR code of the short URL as qr-<slug>.svg', function (): void {
    resolve(ShortlinkRepository::class)->save(new Shortlink(slug: 'sale', destination: '/kontakt'));

    $response = $this->actingAs(ShortlinkFixtures::superUser())
        ->get(ShortlinkFixtures::cpUrl('shortlinks.qr', 'sale'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertHeader('Content-Disposition', 'attachment; filename="qr-sale.svg"');

    expect($response->getContent())->toBe(new QrCode()->svg(url('s/sale')));
});

it('answers :dataset for an unknown shortlink with 404', function (string $method, string $route): void {
    $this->actingAs(ShortlinkFixtures::superUser())
        ->json($method, ShortlinkFixtures::cpUrl($route, 'unknown'), shortlinkForm())
        ->assertNotFound();
})->with([
    'edit' => ['get', 'shortlinks.edit'],
    'update' => ['patch', 'shortlinks.update'],
    'destroy' => ['delete', 'shortlinks.destroy'],
    'qr' => ['get', 'shortlinks.qr'],
]);

dataset('shortlink actions', [
    'index' => ['get', 'shortlinks.index', 'view shortlinks', 200],
    'qr' => ['get', 'shortlinks.qr', 'view shortlinks', 200],
    'create' => ['get', 'shortlinks.create', 'create shortlinks', 200],
    'store' => ['post', 'shortlinks.store', 'create shortlinks', 422],
    'edit' => ['get', 'shortlinks.edit', 'edit shortlinks', 200],
    'update' => ['patch', 'shortlinks.update', 'edit shortlinks', 422],
    'destroy' => ['delete', 'shortlinks.destroy', 'delete shortlinks', 204],
]);

it('allows :dataset with its permission', function (string $method, string $route, string $permission, int $status): void {
    resolve(ShortlinkRepository::class)->save(new Shortlink(slug: 'existing', destination: '/kontakt'));

    $this->actingAs(ShortlinkFixtures::userWith($permission))
        ->json($method, ShortlinkFixtures::cpUrl($route, ['slug' => 'existing']))
        ->assertStatus($status);
})->with('shortlink actions');

it('forbids :dataset without its permission', function (string $method, string $route, string $permission): void {
    resolve(ShortlinkRepository::class)->save(new Shortlink(slug: 'existing', destination: '/kontakt'));
    $others = array_values(array_diff(['view shortlinks', 'edit shortlinks', 'create shortlinks', 'delete shortlinks'], [$permission]));

    $this->actingAs(ShortlinkFixtures::userWith(...$others))
        ->json($method, ShortlinkFixtures::cpUrl($route, ['slug' => 'existing']))
        ->assertForbidden();

    expect(resolve(ShortlinkRepository::class)->find('existing'))->not->toBeNull();
})->with('shortlink actions');
