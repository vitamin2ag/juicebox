<?php

declare(strict_types=1);

use Vitamin2\Juicebox\Features\SecureHeaders\ServiceProvider as SecureHeadersServiceProvider;
use Vitamin2\Juicebox\ServiceProvider;

mutates(ServiceProvider::class);

it('registers the provider of a discovered feature', function (): void {
    expect(app()->getProviders(SecureHeadersServiceProvider::class))->toHaveCount(1);
});
