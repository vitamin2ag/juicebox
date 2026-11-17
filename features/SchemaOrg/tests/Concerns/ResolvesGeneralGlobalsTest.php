<?php

declare(strict_types=1);

use Statamic\Facades\Site;
use Statamic\Globals\GlobalSet;
use Vitamin2\Juicebox\Features\SchemaOrg\Concerns\ResolvesGeneralGlobals;

covers(ResolvesGeneralGlobals::class);

it('reads the entity type a localization inherits from its origin', function (): void {
    config(['statamic.system.multisite' => true]);
    Site::setSites([
        'default' => ['name' => 'English', 'url' => 'http://site.test/', 'locale' => 'en_US'],
        'de' => ['name' => 'Deutsch', 'url' => 'http://site.test/de/', 'locale' => 'de_CH'],
    ]);

    $set = new GlobalSet;
    $set->handle('general');
    $set->sites(['default' => null, 'de' => 'default']);
    $set->save();
    $origin = $set->makeLocalization('default');
    $origin->data(['json_ld_entity' => 'Organization']);
    $origin->save();
    $set->makeLocalization('de')->save();

    Site::setCurrent('de');

    $globals = new class
    {
        use ResolvesGeneralGlobals {
            entityType as public;
        }
    };

    expect($globals->entityType())->toBe('Organization');
});
