<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Statamic\Statamic;
use Vitamin2\Juicebox\Features\SchemaOrg\ServiceProvider;

covers(ServiceProvider::class);

/**
 * Boots the feature in an environment and runs what `statamic:install` runs after it, with the
 * install command of the feature replaced; returns the output of that run.
 */
function runAfterInstalled(string $environment, Closure $install): string
{
    // Callbacks that earlier tests registered would run here as well.
    Statamic::runAfterInstalledCallbacks(Mockery::mock(Command::class)->shouldIgnoreMissing());
    app()->detectEnvironment(fn (): string => $environment);
    // The app booted this provider already, in the testing environment. Booting it again is safe: statamic-features
    // skips a feature it has booted, so only the callback of the environment set above is added.
    new ServiceProvider(app())->boot();

    Artisan::command('statamic:juicebox:schema-org:install', $install);
    Artisan::command('probe:after-installed', function (): void {
        Statamic::runAfterInstalledCallbacks($this);
    });
    Artisan::call('probe:after-installed');

    return Artisan::output();
}

it('installs the general global after the install command in the local environment', function (): void {
    $calls = 0;

    runAfterInstalled('local', function () use (&$calls): void {
        $calls++;
    });

    expect($calls)->toBe(1);
});

it('installs nothing after the install command outside the local environment', function (): void {
    $calls = 0;

    runAfterInstalled('production', function () use (&$calls): void {
        $calls++;
    });

    expect($calls)->toBe(0);
});

it('installs nothing after the install command while the feature is disabled', function (): void {
    config(['juicebox.schema_org.enabled' => false]);
    $calls = 0;

    runAfterInstalled('local', function () use (&$calls): void {
        $calls++;
    });

    expect($calls)->toBe(0);
});

it('only warns when the install fails, so the composer run goes on', function (): void {
    $output = runAfterInstalled('local', function (): void {
        throw new RuntimeException('boom');
    });

    expect($output)->toContain('Could not install the Schema.org global: boom');
});
