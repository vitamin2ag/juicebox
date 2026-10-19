<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects;

use Illuminate\Routing\Router;
use Override;
use Statamic\Facades\Addon;
use Statamic\Facades\Stache;
use Vitamin2\Juicebox\Features\Redirects\Http\Middleware\HandleNotFound;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectStore;
use Vitamin2\Juicebox\FeatureServiceProvider;

class ServiceProvider extends FeatureServiceProvider
{
    #[Override]
    public function register(): void
    {
        parent::register();

        $this->app->singleton(RedirectStore::class, fn (): RedirectStore => new RedirectStore()->directory(base_path(config()->string('juicebox.redirects.store'))));
    }

    /**
     * rias/statamic-redirect registers the same store and handles the same requests,
     * so the feature stays off until rias is removed.
     */
    #[Override]
    public function boot(): void
    {
        parent::boot();

        if (Addon::all()->has('rias/statamic-redirect')) {
            return;
        }

        Stache::registerStore($this->app->make(RedirectStore::class));

        // Statamic creates the statamic.web group in its own boot, and prepending skips a group that does not exist yet.
        $this->app->booted($this->prependMiddleware(...));
    }

    private function prependMiddleware(): void
    {
        $router = $this->app->make(Router::class);
        $router->prependMiddlewareToGroup('web', HandleNotFound::class);
        $router->prependMiddlewareToGroup('statamic.web', HandleNotFound::class);
    }
}
