<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Statamic\Assets\Asset;
use Statamic\Facades\Image;
use Vitamin2\Juicebox\Features\SchemaOrg\Graph\Ids;
use Vitamin2\Juicebox\Features\SchemaOrg\Schemas\Globals\Concerns\HasCommonEntitySchema;
use Vitamin2\Juicebox\Features\SchemaOrg\Schemas\Globals\Corporation;
use Vitamin2\Juicebox\Features\SchemaOrg\Schemas\Globals\LocalBusiness;
use Vitamin2\Juicebox\Features\SchemaOrg\Schemas\Globals\Organization;
use Vitamin2\Juicebox\Features\SchemaOrg\Schemas\Globals\Person;
use Vitamin2\Juicebox\Features\SchemaOrg\Tests\Concerns\SchemaOrgFixtures as Fixtures;

mutates(HasCommonEntitySchema::class);
mutates(Organization::class);
mutates(Corporation::class);
mutates(LocalBusiness::class);
mutates(Person::class);

beforeEach(function (): void {
    Fixtures::useBlueprintStubs();

    Fixtures::assetContainer();

    Fixtures::generalGlobal([
        'json_ld_entity_name' => 'Acme',
        'json_ld_entity_alternate_name' => 'Acme Corp',
        'json_ld_entity_description' => 'A great business.',
        'json_ld_entity_url' => 'https://acme.test',
        'json_ld_entity_email' => 'info@acme.test',
        'json_ld_entity_telephone' => '+41 44 123 45 67',
        'json_ld_entity_address_street' => 'Musterstrasse 1',
        'json_ld_entity_address_zip' => '8000',
        'json_ld_entity_address_city' => 'Zürich',
        'json_ld_entity_address_region' => 'ZH',
        'json_ld_entity_address_country' => 'CH',
        'json_ld_entity_same_as' => [
            'https://www.facebook.com/acme',
            'https://www.instagram.com/acme',
        ],
        'json_ld_entity_legal_name' => 'Acme GmbH & Co. KG',
        'json_ld_entity_founding_date' => '2026-06-19',
        'json_ld_entity_vat_id' => 'CHE-123.456.789 MWST',
        'json_ld_entity_ticker_symbol' => 'NASDAQ:ACME',
        'json_ld_entity_price_range' => '$$',
        'json_ld_entity_latitude' => '0',
        'json_ld_entity_longitude' => '0',
        'json_ld_entity_opening_hours' => [
            ['day' => 'monday', 'opening' => '09:00', 'closing' => '18:00'],
            ['day' => 'tuesday', 'opening' => '09:00', 'closing' => '18:00'],
        ],
    ]);
});

/**
 * @param  array<mixed>  $data
 */
function renderWithDataExpectCommonFields(array $data, string $type): void
{
    expect($data['@type'])->toBe($type)
        ->and($data['name'])->toBe('Acme')
        ->and($data['alternateName'])->toBe('Acme Corp')
        ->and($data['description'])->toBe('A great business.')
        ->and($data['url'])->toBe('https://acme.test')
        ->and($data['email'])->toBe('info@acme.test')
        ->and($data['telephone'])->toBe('+41 44 123 45 67')
        ->and($data['address'])->toBe([
            '@type' => 'PostalAddress',
            'streetAddress' => 'Musterstrasse 1',
            'postalCode' => '8000',
            'addressLocality' => 'Zürich',
            'addressRegion' => 'ZH',
            'addressCountry' => 'CH',
        ])
        ->and($data['sameAs'])->toBe([
            'https://www.facebook.com/acme',
            'https://www.instagram.com/acme',
        ]);
}

it('renders organization common and organization specific fields', function (): void {
    $data = Fixtures::decodeScript((new Organization)->schema()->toScript());

    renderWithDataExpectCommonFields($data, 'Organization');
    expect($data['legalName'])->toBe('Acme GmbH & Co. KG')
        ->and($data['foundingDate'])->toBe('2026-06-19T00:00:00+00:00')
        ->and($data['vatID'])->toBe('CHE-123.456.789 MWST');
});

it('renders corporation common and corporation specific fields', function (): void {
    $data = Fixtures::decodeScript((new Corporation)->schema()->toScript());

    renderWithDataExpectCommonFields($data, 'Corporation');
    expect($data['legalName'])->toBe('Acme GmbH & Co. KG')
        ->and($data['foundingDate'])->toBe('2026-06-19T00:00:00+00:00')
        ->and($data['vatID'])->toBe('CHE-123.456.789 MWST')
        ->and($data['tickerSymbol'])->toBe('NASDAQ:ACME');
});

it('renders local business common and local business specific fields', function (): void {
    $data = Fixtures::decodeScript((new LocalBusiness)->schema()->toScript());

    renderWithDataExpectCommonFields($data, 'LocalBusiness');
    expect($data['priceRange'])->toBe('$$')
        ->and($data['geo'])->toBe([
            '@type' => 'GeoCoordinates',
            'latitude' => '0',
            'longitude' => '0',
        ])
        ->and($data['openingHoursSpecification'])->toBe([
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'https://schema.org/Monday',
                'opens' => '09:00',
                'closes' => '18:00',
            ],
            [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'https://schema.org/Tuesday',
                'opens' => '09:00',
                'closes' => '18:00',
            ],
        ]);
});

it('renders person common entity fields from the global set', function (): void {
    $data = Fixtures::decodeScript((new Person)->schema()->toScript());

    renderWithDataExpectCommonFields($data, 'Person');
});

it('exposes a label for every global schema', function (Organization|Corporation|LocalBusiness|Person $schema, string $label): void {
    expect($schema::label())->toBe($label);
})->with([
    'organization' => [new Organization, 'Organisation'],
    'corporation' => [new Corporation, 'Aktiengesellschaft'],
    'local business' => [new LocalBusiness, 'Lokales Unternehmen'],
    'person' => [new Person, 'Person'],
]);

it('maps every weekday to its schema.org day', function (string $day, string $expected): void {
    Fixtures::generalGlobal([
        'json_ld_entity_opening_hours' => [['day' => $day, 'opening' => '09:00', 'closing' => '18:00']],
    ]);

    $hours = Fixtures::arrayList((new LocalBusiness)->schema()->toArray()['openingHoursSpecification'] ?? []);

    expect($hours)->toHaveCount(1)
        ->and($hours[0]['dayOfWeek'])->toBe('https://schema.org/'.$expected);
})->with([
    'monday' => ['monday', 'Monday'],
    'tuesday' => ['tuesday', 'Tuesday'],
    'wednesday' => ['wednesday', 'Wednesday'],
    'thursday' => ['thursday', 'Thursday'],
    'friday' => ['friday', 'Friday'],
    'saturday' => ['saturday', 'Saturday'],
    'sunday' => ['sunday', 'Sunday'],
]);

it('skips incomplete or unknown opening hours rows', function (): void {
    Fixtures::generalGlobal([
        'json_ld_entity_opening_hours' => [
            ['day' => 'someday', 'opening' => '09:00', 'closing' => '18:00'],
            ['day' => 'monday', 'opening' => '09:00', 'closing' => ''],
        ],
    ]);

    expect((new LocalBusiness)->schema()->toArray())->not->toHaveKey('openingHoursSpecification');
});

/**
 * A container is only public when its disk has a url, so the fake disk is
 * given one unless the test is about a private container.
 *
 * @return array<mixed>
 */
function renderWithDataOrganizationAfterLogo(string $path, bool $accessible = true, Organization|Corporation|LocalBusiness|Person $schema = new Organization): array
{
    Storage::fake('local', $accessible ? ['url' => '/assets'] : [])->put($path, 'logo');
    Fixtures::generalGlobal(['json_ld_entity_logo' => $path]);

    return $schema->schema()->toArray();
}

it('renders an svg logo resolved against the current site, not the request', function (): void {
    expect(renderWithDataOrganizationAfterLogo('logo.svg')['logo'] ?? null)->toBe(Ids::site().'/assets/logo.svg');
});

it('renders the logo on every global schema', function (Organization|Corporation|LocalBusiness|Person $schema, string $property): void {
    expect(renderWithDataOrganizationAfterLogo('logo.svg', schema: $schema)[$property] ?? null)->toBe(Ids::site().'/assets/logo.svg');
})->with([
    'organization' => [new Organization, 'logo'],
    'corporation' => [new Corporation, 'logo'],
    'local business' => [new LocalBusiness, 'logo'],
    'person' => [new Person, 'image'],
]);

it('renders a raster logo as a 512px png derived from the asset', function (): void {
    Image::shouldReceive('manipulate')
        ->once()
        ->with(Mockery::type(Asset::class), ['w' => 512, 'h' => 512, 'fit' => 'fill', 'fm' => 'png'])
        ->andReturn('/img/asset/logo.png');

    expect(renderWithDataOrganizationAfterLogo('logo.jpg')['logo'] ?? null)->toBe(Ids::site().'/img/asset/logo.png');
});

it('leaves an absolute logo url untouched', function (): void {
    Image::shouldReceive('manipulate')->once()->andReturn('https://cdn.test/logo.png');

    expect(renderWithDataOrganizationAfterLogo('logo.jpg')['logo'] ?? null)->toBe('https://cdn.test/logo.png');
});

it('has no logo when the manipulation yields no url', function (): void {
    Image::shouldReceive('manipulate')->once()->andReturn(null);

    expect(renderWithDataOrganizationAfterLogo('logo.jpg'))->not->toHaveKey('logo');
});

it('has no logo when the asset container is private', function (): void {
    expect(renderWithDataOrganizationAfterLogo('logo.svg', accessible: false))->not->toHaveKey('logo');
});

it('has no geo coordinates unless both latitude and longitude are set', function (string $key): void {
    Fixtures::generalGlobal([$key => '47.37']);

    expect((new LocalBusiness)->schema()->toArray())->not->toHaveKey('geo');
})->with(['latitude only' => ['json_ld_entity_latitude'], 'longitude only' => ['json_ld_entity_longitude']]);
