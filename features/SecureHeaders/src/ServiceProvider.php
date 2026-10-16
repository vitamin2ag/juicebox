<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SecureHeaders;

use Illuminate\Routing\Router;
use Override;
use Vitamin2\Juicebox\Features\SecureHeaders\Http\Middleware\SetSecureHeaders;
use Vitamin2\Juicebox\FeatureServiceProvider;

class ServiceProvider extends FeatureServiceProvider
{
    #[Override]
    public function bootAddon(): void
    {
        $this->app->make(Router::class)->pushMiddlewareToGroup('web', SetSecureHeaders::class);
    }
}
