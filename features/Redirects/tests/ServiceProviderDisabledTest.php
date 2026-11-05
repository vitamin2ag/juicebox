<?php

declare(strict_types=1);

use Statamic\Facades\Stache;
use Vitamin2\Juicebox\Features\Redirects\ServiceProvider;

mutates(ServiceProvider::class);

/*
 * The switch is read while the app boots, so these tests turn it off in beforeAll(), as a project does.
 */
beforeAll(function (): void {
    putenv('JUICEBOX_REDIRECTS_ENABLED=false');
});

afterAll(function (): void {
    putenv('JUICEBOX_REDIRECTS_ENABLED');
});

it('leaves the redirect store out of the Stache while the feature is disabled', function (): void {
    expect(Stache::stores()->has('redirects'))->toBeFalse();
});
