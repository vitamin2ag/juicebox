<?php

declare(strict_types=1);

use Statamic\Facades\Site;
use Vitamin2\Juicebox\Features\SchemaOrg\Schemas\Globals\Corporation;
use Vitamin2\Juicebox\Features\SchemaOrg\Schemas\Globals\LocalBusiness;
use Vitamin2\Juicebox\Features\SchemaOrg\Schemas\Globals\Organization;
use Vitamin2\Juicebox\Features\SchemaOrg\Schemas\Globals\Person;
use Vitamin2\Juicebox\Features\SchemaOrg\Tests\Concerns\SchemaOrgFixtures as Fixtures;

mutates(Organization::class);
mutates(Corporation::class);
mutates(LocalBusiness::class);
mutates(Person::class);

it('renders without general globals', function (Organization|Corporation|LocalBusiness|Person $schema, string $type): void {
    $script = $schema->schema()->toScript();

    expect($script)->toContain('"@type":"'.$type.'"');
})->with([
    'organization' => [new Organization, 'Organization'],
    'corporation' => [new Corporation, 'Corporation'],
    'local business' => [new LocalBusiness, 'LocalBusiness'],
    'person' => [new Person, 'Person'],
]);

it('falls back to the current site for name and url', function (Organization|Corporation|LocalBusiness|Person $schema): void {
    Site::setSites(['default' => ['name' => 'Acme Site', 'url' => 'https://acme.test', 'locale' => 'en_US']]);

    $data = $schema->schema()->toArray();

    expect($data['name'])->toBe('Acme Site')
        ->and($data['url'])->toBe('https://acme.test');
})->with([
    'organization' => [new Organization],
    'corporation' => [new Corporation],
    'local business' => [new LocalBusiness],
    'person' => [new Person],
]);

it('omits name and url without a current site', function (Organization|Corporation|LocalBusiness|Person $schema): void {
    Fixtures::withoutCurrentSite();

    expect($schema->schema()->toArray())->not->toHaveKeys(['name', 'url']);
})->with([
    'organization' => [new Organization],
    'corporation' => [new Corporation],
    'local business' => [new LocalBusiness],
    'person' => [new Person],
]);
