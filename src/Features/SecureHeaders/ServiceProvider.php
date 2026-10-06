<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SecureHeaders;

use Override;
use Vitamin2\Juicebox\Features\FeatureServiceProvider;
use Vitamin2\Juicebox\Features\SecureHeaders\Http\Middleware\SetSecureHeaders;

class ServiceProvider extends FeatureServiceProvider
{
    #[Override]
    protected $middlewareGroups = [
        'web' => [
            SetSecureHeaders::class,
        ],
    ];
}
