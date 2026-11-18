<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Vitamin2\Juicebox\Features\SchemaOrg\Console\Commands\InstallGeneralGlobal;
use Vitamin2\Juicebox\Features\SchemaOrg\Dictionaries\Schemas;
use Vitamin2\Juicebox\Features\SchemaOrg\Tests\Concerns\SchemaOrgFixtures as Fixtures;

covers(Schemas::class, InstallGeneralGlobal::class);

it('translates a fieldset label of the control panel into :dataset', function (string $locale, string $expected): void {
    app()->setLocale($locale);

    expect(Collection::wrap(resolve('translator')->toJson())->get('*.Street'))->toBe($expected);
})->with([
    'German' => ['de', 'Straße'],
    'Swiss German' => ['de_CH', 'Strasse'],
    'French' => ['fr', 'Rue'],
]);

it('translates the schema labels of the type picker into :dataset', function (string $locale, string $expected): void {
    app()->setLocale($locale);

    $dictionary = new Schemas;
    $dictionary->setConfig(['category' => 'Globals']);

    expect($dictionary->options())->toHaveKey('LocalBusiness', $expected);
})->with([
    'German' => ['de', 'Lokales Unternehmen'],
    'Swiss German' => ['de_CH', 'Lokales Unternehmen'],
    'French' => ['fr', 'Commerce local'],
]);

it('translates the output of the install command', function (): void {
    $path = Fixtures::temporaryBlueprintsPath();
    app()->setLocale('de');

    Artisan::call('statamic:juicebox:schema-org:install');

    expect(Artisan::output())->toContain('Das Global [general] wurde erstellt.');

    Fixtures::deleteDirectory($path);
});
