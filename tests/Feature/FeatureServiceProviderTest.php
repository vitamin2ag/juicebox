<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Vitamin2\Juicebox\Features\SecureHeaders\Http\Middleware\SetSecureHeaders;
use Vitamin2\Juicebox\Features\SecureHeaders\ServiceProvider as SecureHeadersServiceProvider;
use Vitamin2\Juicebox\FeatureServiceProvider;

mutates(FeatureServiceProvider::class);

it('merges feature config under the addon key', function (): void {
    expect(config('juicebox.secure_headers'))->toBeArray();
});

it('publishes feature config under the addon with an addon-prefixed tag', function (): void {
    $paths = ServiceProvider::pathsToPublish(SecureHeadersServiceProvider::class, 'juicebox-secure-headers-config');

    expect(array_values($paths))->toBe([config_path('juicebox/secure_headers.php')]);
});

it('boots the Statamic addon features of a feature provider', function (): void {
    expect(resolve('router')->getMiddlewareGroups()['web'])->toContain(SetSecureHeaders::class);
});
