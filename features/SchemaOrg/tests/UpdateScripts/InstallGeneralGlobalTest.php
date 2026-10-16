<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Vitamin2\Juicebox\Features\SchemaOrg\UpdateScripts\InstallGeneralGlobal;

mutates(InstallGeneralGlobal::class);

it('always wants to update', function (): void {
    $script = new InstallGeneralGlobal('vitamin2/juicebox');

    expect($script->shouldUpdate('0.3.0', '0.2.0'))->toBeTrue()
        ->and($script->shouldUpdate('0.2.0', '0.2.0'))->toBeTrue();
});

it('delegates to the install command', function (): void {
    $calls = 0;

    // Replaces the real command, which the command tests cover, so this only
    // proves the update script invokes it.
    Artisan::command('statamic:juicebox:schema-org:install', function () use (&$calls): void {
        $calls++;
    });

    new InstallGeneralGlobal('vitamin2/juicebox')->update();

    expect($calls)->toBe(1);
});
