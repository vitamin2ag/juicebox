<?php

declare(strict_types=1);

use Statamic\Providers\AddonServiceProvider;
use Vitamin2\Juicebox\Features\SecureHeaders\ServiceProvider as SecureHeadersServiceProvider;
use Vitamin2\Juicebox\ServiceProvider;

mutates(ServiceProvider::class);

it('registers the provider of a discovered feature', function (): void {
    expect(app()->getProviders(SecureHeadersServiceProvider::class))->toHaveCount(1);
});

it('boots before the providers of its features', function (): void {
    $order = collect(app()->getProviders(AddonServiceProvider::class))
        ->whereInstanceOf(AddonServiceProvider::class)
        ->map(fn (AddonServiceProvider $provider): string => $provider::class)
        ->intersect([ServiceProvider::class, SecureHeadersServiceProvider::class])
        ->values()
        ->all();

    expect($order)->toBe([ServiceProvider::class, SecureHeadersServiceProvider::class]);
});
