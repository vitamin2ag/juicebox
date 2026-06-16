<?php

namespace Vitamin2\Juicebox\Features\SecureHeaders;

use Vitamin2\Juicebox\Features\FeatureServiceProvider;
use Vitamin2\Juicebox\Features\SecureHeaders\Http\Middleware\SetSecureHeaders;

class ServiceProvider extends FeatureServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        $this->app['router']->pushMiddlewareToGroup('web', SetSecureHeaders::class);
    }
}
