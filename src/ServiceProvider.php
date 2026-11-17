<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox;

use Edalzell\Features\Concerns\HasFeatures;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Override;
use Statamic\Statamic;
use Vitamin2\Juicebox\Install\IgnorePublishedAssets;
use Vitamin2\Juicebox\Tags\Juicebox;

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
        $this->bootProxyTag();
        $this->bootIgnoredAssets();
    }

    /**
     * statamic-features only registers the tags inside feature folders, so the
     * core proxy tag in `src/Tags` is registered here.
     */
    private function bootProxyTag(): void
    {
        Juicebox::register();
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
