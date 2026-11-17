<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox;

use Edalzell\Features\Concerns\HasFeatures;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Override;
use Vitamin2\Juicebox\Tags\Juicebox;

class ServiceProvider extends LaravelServiceProvider
{
    use HasFeatures;

    #[Override]
    public function register(): void
    {
        $this->registerFeatures();
    }

    /**
     * statamic-features only registers the tags inside feature folders, so the
     * core proxy tag in `src/Tags` is registered here.
     */
    public function boot(): void
    {
        Juicebox::register();
    }
}
