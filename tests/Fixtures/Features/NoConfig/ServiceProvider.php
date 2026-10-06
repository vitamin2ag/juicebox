<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Tests\Fixtures\Features\NoConfig;

use Vitamin2\Juicebox\Features\FeatureServiceProvider;

class ServiceProvider extends FeatureServiceProvider
{
    public static bool $booted = false;

    public function bootFeature(): void
    {
        static::$booted = true;
    }
}
