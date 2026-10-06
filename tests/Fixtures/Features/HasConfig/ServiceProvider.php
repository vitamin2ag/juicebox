<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Tests\Fixtures\Features\HasConfig;

use Override;
use Vitamin2\Juicebox\Features\FeatureServiceProvider;

class ServiceProvider extends FeatureServiceProvider
{
    #[Override]
    protected $config = true;

    protected function featureConfigPath(): string
    {
        return __DIR__.'/../../config/has_config.php';
    }
}
