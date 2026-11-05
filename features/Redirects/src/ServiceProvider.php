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

        $this->app->singleton(
            RedirectStore::class,
            fn (): RedirectStore => new RedirectStore()
                ->directory(base_path(config()->string('juicebox.redirects.store'))),
        );
    }

    /**
     * The feature switches itself off while rias/statamic-redirect is installed, because that package
     * registers the same store and handles the same requests. The listeners boot either way and check
     * the same switch.
     */
    #[Override]
    protected function bootFeature(): void
    {
        if (Addon::all()->has('rias/statamic-redirect')) {
            config(['juicebox.redirects.enabled' => false]);
        }

        if (! config('juicebox.redirects.enabled')) {
            return;
        }

        $this->registerStore();
        $this->registerMiddleware();
    }

    private function registerStore(): void
    {
        Stache::registerStore($this->app->make(RedirectStore::class));
    }

    /**
     * Statamic creates the statamic.web group in a booted callback of its own, which #[BootAfter] does not wait for, and prepending skips a group that does not exist yet.
     */
    private function registerMiddleware(): void
    {
        $this->app->booted(function (): void {
            $router = $this->app->make(Router::class);
            $router->prependMiddlewareToGroup('web', HandleNotFound::class);
            $router->prependMiddlewareToGroup('statamic.web', HandleNotFound::class);
        });
    }
}
