<?php

declare(strict_types=1);

use Edalzell\StatamicFeatures\ServiceProvider as StatamicFeaturesServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Statamic\Statamic;
use Vitamin2\Juicebox\Features\SecureHeaders\ServiceProvider as SecureHeadersServiceProvider;
use Vitamin2\Juicebox\ServiceProvider;
use Vitamin2\Juicebox\Tests\Feature\__Fixtures__\WebApplication;

covers(ServiceProvider::class);

it('registers the provider of a discovered feature', function (): void {
    expect(app()->getProviders(SecureHeadersServiceProvider::class))->toHaveCount(1);
});

it('loads statamic-features so feature routes and blueprints are registered like in an application', function (): void {
    expect(app()->getProviders(StatamicFeaturesServiceProvider::class))->toHaveCount(1);
});

describe('after the install command', function (): void {
    beforeEach(function (): void {
        $this->site = sys_get_temp_dir().'/juicebox-install-'.getmypid();

        File::ensureDirectoryExists($this->site);
        app()->setBasePath($this->site);
        app()->detectEnvironment(fn (): string => 'local');
        Process::path($this->site)->run(['git', 'init', '-q']);
        // Callbacks registered by earlier tests would otherwise run against this site.
        Statamic::runAfterInstalledCallbacks(Mockery::mock(Command::class)->shouldIgnoreMissing());
        File::delete($this->site.'/.gitignore');
    });

    afterEach(function (): void {
        File::deleteDirectory($this->site);
    });

    it('ignores the published assets', function (): void {
        new ServiceProvider(app())->boot();
        Statamic::runAfterInstalledCallbacks(Mockery::mock(Command::class)->shouldIgnoreMissing());

        expect(File::get($this->site.'/.gitignore'))->toBe("/public/vendor/redirects/\n");
    });

    it('registers nothing outside the local environment', function (): void {
        app()->detectEnvironment(fn (): string => 'production');

        new ServiceProvider(app())->boot();
        Statamic::runAfterInstalledCallbacks(Mockery::mock(Command::class)->shouldIgnoreMissing());

        expect($this->site.'/.gitignore')->not->toBeFile();
    });

    it('registers nothing outside the console', function (): void {
        new ServiceProvider(new WebApplication)->boot();
        Statamic::runAfterInstalledCallbacks(Mockery::mock(Command::class)->shouldIgnoreMissing());

        expect($this->site.'/.gitignore')->not->toBeFile();
    });
});

it('registers the juicebox tag so a template can render it', function (): void {
    $view = view()->file(__DIR__.'/__Fixtures__/views/schema.antlers.html');

    expect($view->render())->toStartWith('<script type="application/ld+json">');
});
