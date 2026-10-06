<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Tests\Features;

use Vitamin2\Juicebox\Tests\Fixtures\Features\HasConfig\ServiceProvider as HasConfigServiceProvider;
use Vitamin2\Juicebox\Tests\Fixtures\Features\NoConfig\ServiceProvider as NoConfigServiceProvider;
use Vitamin2\Juicebox\Tests\TestCase;

abstract class FeatureServiceProviderTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            HasConfigServiceProvider::class,
            NoConfigServiceProvider::class,
        ]);
    }
}
