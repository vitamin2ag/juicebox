<?php

namespace Vitamin2\Juicebox\Tests;

use Override;
use Statamic\Testing\AddonTestCase;
use Vitamin2\Juicebox\Features\SecureHeaders\ServiceProvider as SecureHeadersServiceProvider;
use Vitamin2\Juicebox\ServiceProvider;

abstract class TestCase extends AddonTestCase
{
    #[Override]
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            SecureHeadersServiceProvider::class,
        ]);
    }
}
