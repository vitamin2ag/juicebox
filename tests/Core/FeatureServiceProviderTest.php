<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Statamic\Addons\Manifest;
use Statamic\Facades\Addon;
use Vitamin2\Juicebox\Features\FeatureServiceProvider;
use Vitamin2\Juicebox\Features\SecureHeaders\ServiceProvider as SecureHeadersServiceProvider;
use Vitamin2\Juicebox\Tests\Core\FeatureServiceProviderTestCase;
use Vitamin2\Juicebox\Tests\Fixtures\Features\HasConfig\ServiceProvider as HasConfigServiceProvider;
use Vitamin2\Juicebox\Tests\Fixtures\Features\NoConfig\ServiceProvider as NoConfigServiceProvider;

pest()->extend(FeatureServiceProviderTestCase::class);

mutates(FeatureServiceProvider::class);

it('merges feature config under namespaced key', function (): void {
    expect(config('juicebox.has_config'))->toBeArray()
        ->and(config('juicebox.has_config.value'))->toBe('fixture');
});

it('registers publish tag and nested path for feature config', function (): void {
    $paths = ServiceProvider::pathsToPublish(
        HasConfigServiceProvider::class,
        'statamic-juicebox-has-config-config',
    );

    expect($paths)->toHaveCount(1)
        ->and(reset($paths))->toBe(config_path('juicebox/has_config.php'))
        ->and(array_key_first($paths))->toEndWith('config/has_config.php');
});

it('calls bootFeature on extending class', function (): void {
    expect(NoConfigServiceProvider::$booted)->toBeTrue();
});

it('skips config merge when feature has no config file', function (): void {
    expect(config('juicebox.no_config'))->toBeNull();
});

it('resolves the config path inside the addon directory', function (): void {
    $provider = new SecureHeadersServiceProvider(app());

    expect(new ReflectionMethod($provider, 'featureConfigPath')->invoke($provider))
        ->toBe(dirname(__DIR__, 2).'/config/secure_headers.php');
});

it('throws when the addon cannot be resolved', function (): void {
    Addon::shouldReceive('all')->andReturn(collect());
    app()->instance(Manifest::class, Mockery::mock(Manifest::class)->shouldReceive('build')->getMock());

    $provider = new NoConfigServiceProvider(app());

    new ReflectionMethod($provider, 'getAddon')->invoke($provider);
})->throws(RuntimeException::class, 'Unable to resolve the addon for '.NoConfigServiceProvider::class.'.');
