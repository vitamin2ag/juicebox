<?php

declare(strict_types=1);

use Edalzell\StatamicFeatures\ServiceProvider as StatamicFeaturesServiceProvider;
use Vitamin2\Juicebox\Features\SecureHeaders\ServiceProvider as SecureHeadersServiceProvider;
use Vitamin2\Juicebox\ServiceProvider;

mutates(ServiceProvider::class);

it('registers the provider of a discovered feature', function (): void {
    expect(app()->getProviders(SecureHeadersServiceProvider::class))->toHaveCount(1);
});

it('loads statamic-features so feature routes and blueprints are registered like in an application', function (): void {
    expect(app()->getProviders(StatamicFeaturesServiceProvider::class))->toHaveCount(1);
});
