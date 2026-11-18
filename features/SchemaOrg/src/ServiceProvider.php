<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg;

use Override;
use Vitamin2\Juicebox\FeatureServiceProvider;

class ServiceProvider extends FeatureServiceProvider
{
    #[Override]
    protected function options(): array
    {
        return [
            'statamic.vite' => ['input' => 'resources/js/cp.ts', 'publicDirectory' => 'resources', 'buildDirectory' => 'dist'],
        ];
    }
}
