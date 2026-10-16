<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox;

use Edalzell\Features\Concerns\HasFeatures;
use Override;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    use HasFeatures;

    /**
     * Statamic only boots an addon's root items (fieldsets, blueprints, views, ...)
     * while none of its providers has finished booting. Registering the features
     * once every provider is registered keeps this provider ahead of theirs.
     */
    #[Override]
    public function register(): void
    {
        $this->app->booting($this->discoverFeatures(...));
    }

    private function discoverFeatures(): void
    {
        $this->registerFeatures();
    }
}
