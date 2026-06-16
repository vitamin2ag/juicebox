<?php

namespace Vitamin2\Juicebox\Tests\Fixtures;

use Vitamin2\Juicebox\Features\FeatureServiceProvider;

class WithConfigProvider extends FeatureServiceProvider
{
    protected function featuresPath(): string
    {
        return __DIR__.'/WithConfig';
    }

    protected function name(): string
    {
        return 'WithConfig';
    }
}
