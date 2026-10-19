<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Statamic\Facades\Blueprint;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Fields\Blueprint as FieldsBlueprint;
use Statamic\Fields\Tab;
use Statamic\Globals\GlobalSet as GlobalSetModel;
use Statamic\Globals\Variables;
use Vitamin2\Juicebox\Features\SchemaOrg\Console\Commands\InstallGeneralGlobal;
use Vitamin2\Juicebox\Features\SchemaOrg\Tests\Concerns\SchemaOrgFixtures as Fixtures;

mutates(InstallGeneralGlobal::class);

function runInstallCommand(): string
{
    expect(Artisan::call('statamic:juicebox:schema-org:install'))->toBe(0);

    return Artisan::output();
}

function jsonLdTabDisplay(FieldsBlueprint $blueprint): ?string
{
    $tab = $blueprint->tabs()->get('json_ld');

    $display = $tab instanceof Tab ? $tab->display() : null;

    return is_string($display) ? $display : null;
}

/**
 * @return list<string>
 */
function localizedSites(GlobalSetModel $set): array
{
    $localizations = $set->localizations();

    return $localizations instanceof Collection ? array_values(array_map(strval(...), $localizations->keys()->all())) : [];
}

function generalBlueprintFile(): string
{
    return (string) file_get_contents(config()->string('statamic.system.blueprints_path').'/globals/general.yaml');
}

function generalSet(): GlobalSetModel
{
    $set = GlobalSet::find('general');

    return $set instanceof GlobalSetModel ? $set : throw new RuntimeException('General global set missing.');
}

function currentSiteValue(GlobalSetModel $set, string $key): mixed
{
    $variables = $set->inCurrentSite();

    return $variables instanceof Variables ? $variables->get($key) : throw new RuntimeException('No variables for current site.');
}

function generalBlueprint(): FieldsBlueprint
{
    $blueprint = Blueprint::find('globals.general');

    return $blueprint instanceof FieldsBlueprint ? $blueprint : throw new RuntimeException('General blueprint missing.');
}

beforeEach(function (): void {
    $this->blueprintsPath = Fixtures::temporaryBlueprintsPath();
});

afterEach(function (): void {
    Fixtures::deleteDirectory($this->blueprintsPath);
});

it('creates the global and blueprint when missing', function (): void {
    expect(GlobalSet::find('general'))->toBeNull()
        ->and(Blueprint::find('globals.general'))->toBeNull();

    runInstallCommand();

    $set = generalSet();
    expect($set->title())->toBe('General')
        ->and($set->existsIn('default'))->toBeTrue();

    $blueprint = generalBlueprint();
    expect($blueprint->title())->toBe('General')
        ->and($blueprint->hasTab('json_ld'))->toBeTrue()
        ->and($blueprint->hasField('json_ld_entity'))->toBeTrue()
        ->and(jsonLdTabDisplay($blueprint))->toBe('JSON-LD')
        ->and(generalBlueprintFile())->toContain('json_ld');
});

it('creates a localization of the global for every site', function (): void {
    config(['statamic.system.multisite' => true]);
    Site::setSites([
        'site_a' => ['name' => 'Site A', 'url' => 'http://site-a.test/', 'locale' => 'en_US'],
        'site_b' => ['name' => 'Site B', 'url' => 'http://site-b.test/', 'locale' => 'de_CH'],
    ]);

    runInstallCommand();

    $set = generalSet();

    expect(localizedSites($set))->toBe(['site_a', 'site_b'])
        ->and($set->existsIn('site_a'))->toBeTrue()
        ->and($set->existsIn('site_b'))->toBeTrue();
});

it('reports what it created', function (): void {
    expect(runInstallCommand())
        ->toContain('Created the [general] global.')
        ->toContain('Created the [globals.general] blueprint.');
});

it('leaves an existing global untouched', function (): void {
    $set = new GlobalSetModel;
    $set->handle('general');
    $set->title('Custom Title');
    $set->save();
    $localization = $set->makeLocalization('default');
    $localization = $localization instanceof Variables ? $localization : throw new RuntimeException('Localization missing.');
    $localization->data(['custom_field' => 'custom_value']);
    $localization->save();

    runInstallCommand();

    $set = generalSet();
    expect($set->title())->toBe('Custom Title')
        ->and(currentSiteValue($set, 'custom_field'))->toBe('custom_value');
});

it('appends the tab without touching existing blueprint fields', function (): void {
    $blueprint = new FieldsBlueprint;
    $blueprint->setHandle('general');
    $blueprint->setNamespace('globals');
    $blueprint->setContents([
        'title' => 'Site Settings',
        'tabs' => [
            'main' => [
                'sections' => [
                    ['fields' => [['handle' => 'custom_field', 'field' => ['type' => 'text']]]],
                ],
            ],
        ],
    ]);
    $blueprint->save();

    $output = runInstallCommand();

    $blueprint = generalBlueprint();
    expect($output)->toContain('Added the [json_ld] tab to the [globals.general] blueprint.')
        ->and($blueprint->title())->toBe('Site Settings')
        ->and($blueprint->hasField('custom_field'))->toBeTrue()
        ->and($blueprint->hasTab('json_ld'))->toBeTrue()
        ->and($blueprint->hasField('json_ld_entity'))->toBeTrue()
        ->and(generalBlueprintFile())->toContain('Site Settings')->toContain('json_ld');
});

it('is idempotent', function (): void {
    runInstallCommand();

    expect(runInstallCommand())->toBeEmpty()
        ->and(generalBlueprint()->tabs())->toHaveCount(1);
});
