<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox;

use Edalzell\Features\Concerns\HasFeatures;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Override;
use Statamic\Statamic;
use Vitamin2\Juicebox\Install\IgnorePublishedAssets;

class ServiceProvider extends LaravelServiceProvider
{
    use HasFeatures;

    #[Override]
    public function register(): void
    {
        $this->registerFeatures();
    }

    public function boot(): void
    {
        $this->bootIgnoredAssets();
    }

    private function bootIgnoredAssets(): void
    {
        // Only a developer's machine needs the lines; a deployed checkout must not change its .gitignore.
        if (! $this->app->runningInConsole() || ! $this->app->isLocal()) {
            return;
        }

        Statamic::afterInstalled((new IgnorePublishedAssets)(...));
    }
}
