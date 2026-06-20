<?php

namespace Vitamin2\Juicebox\Tests\Fixtures\Features\HasConfig;

use Vitamin2\Juicebox\Features\FeatureServiceProvider;

class ServiceProvider extends FeatureServiceProvider
{
    protected $config = true;

    protected function featureConfigPath(): string
    {
        return __DIR__.'/../../config/has_config.php';
    }
}
