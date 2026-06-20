<?php

namespace Vitamin2\Juicebox\Features\SecureHeaders;

use Vitamin2\Juicebox\Features\FeatureServiceProvider;
use Vitamin2\Juicebox\Features\SecureHeaders\Http\Middleware\SetSecureHeaders;

class ServiceProvider extends FeatureServiceProvider
{
    protected $middlewareGroups = [
        'web' => [
            SetSecureHeaders::class,
        ],
    ];

    public function bootFeature(): void
    {
        //
    }
}
