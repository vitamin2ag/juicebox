<?php

namespace Vitamin2\Juicebox\Features\SecureHeaders;

use Statamic\Providers\AddonServiceProvider;
use Vitamin2\Juicebox\Features\SecureHeaders\Http\Middleware\SetSecureHeaders;

class ServiceProvider extends AddonServiceProvider
{
    protected $middlewareGroups = [
        'web' => [
            SetSecureHeaders::class,
        ],
    ];

    public function bootAddon(): void
    {
        $this->publishes([
            __DIR__.'/../../../config/secure_headers.php' => config_path('juicebox/secure_headers.php'),
        ], 'statamic-juicebox-secure-headers-config');

        $this->mergeConfigFrom(__DIR__.'/../../../config/secure_headers.php', 'juicebox.secure_headers');
    }
}
