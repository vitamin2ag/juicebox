<?php

namespace Vitamin2\Juicebox\Tests\Fixtures;

use Vitamin2\Juicebox\Features\FeatureServiceProvider;

class WithoutConfigProvider extends FeatureServiceProvider
{
    protected function featuresPath(): string
    {
        return __DIR__.'/WithoutConfig';
    }

    protected function name(): string
    {
        return 'WithoutConfig';
    }
}
