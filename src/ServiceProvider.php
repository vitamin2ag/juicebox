<?php

namespace Vitamin2\Juicebox;

use Statamic\Providers\AddonServiceProvider;
use Vitamin2\Juicebox\Http\Middleware\SetSecureHeaders;

class ServiceProvider extends AddonServiceProvider
{
    protected $middlewareGroups = [
        'web' => [
            SetSecureHeaders::class,
        ],
    ];

    public function bootAddon()
    {
        //
    }
}
