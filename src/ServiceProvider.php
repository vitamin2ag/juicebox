<?php

namespace Vitamin2\Juicebox;

use Edalzell\Features\Concerns\HasFeatures;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    use HasFeatures;

    public function register(): void
    {
        $this->registerFeatures();
    }
}
