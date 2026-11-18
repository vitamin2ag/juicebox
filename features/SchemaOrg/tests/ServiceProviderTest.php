<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Statamic\Statamic;
use Vitamin2\Juicebox\Features\SchemaOrg\ServiceProvider;

covers(ServiceProvider::class);

it('registers the control panel build with Vite', function (): void {
    $feature = dirname(__DIR__);

    expect(Statamic::availableVites(request()))->toHaveKey('schema-org', [
        'hotFile' => "{$feature}/resources/hot",
        'buildDirectory' => 'vendor/schema-org/dist',
        'input' => 'resources/js/cp.ts',
    ]);
});

it('publishes the control panel build with the schema-org tag', function (): void {
    $feature = dirname(__DIR__);
    $paths = LaravelServiceProvider::pathsToPublish(ServiceProvider::class, 'schema-org');

    expect($paths)->toBe(["{$feature}/resources/dist/" => public_path('vendor/schema-org/dist/')]);
});
