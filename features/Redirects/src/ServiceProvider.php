<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects;

use Illuminate\Routing\Router;
use Override;
use Statamic\CP\Navigation\Nav as Navigation;
use Statamic\Facades\Addon;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Facades\Stache;
use Vitamin2\Juicebox\Features\Redirects\Filters\RedirectEnabled;
use Vitamin2\Juicebox\Features\Redirects\Filters\RedirectMatchType;
use Vitamin2\Juicebox\Features\Redirects\Filters\RedirectType;
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
     * registers the same store and handles the same requests. The routes, listeners and commands boot
     * either way and check the same switch.
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
        $this->registerFilters();
        $this->registerPermissions();
        $this->registerNavigation();
        $this->registerMiddleware();
    }

    private function registerStore(): void
    {
        Stache::registerStore($this->app->make(RedirectStore::class));
    }

    private function registerFilters(): void
    {
        RedirectEnabled::register();
        RedirectMatchType::register();
        RedirectType::register();
    }

    private function registerPermissions(): void
    {
        Permission::extend(function (): void {
            Permission::group('redirects', 'Redirects', function (): void {
                Permission::register('view redirects')->label('View Redirects')->children([
                    Permission::make('edit redirects')->label('Edit Redirects')->children([
                        Permission::make('create redirects')->label('Create Redirects'),
                        Permission::make('delete redirects')->label('Delete Redirects'),
                    ]),
                ]);
            });
        });
    }

    private function registerNavigation(): void
    {
        Nav::extend(function (Navigation $nav): void {
            $item = $nav->create('Redirects');
            $item->section('Tools');
            $item->route('redirects.index');
            $item->icon('arrow-up-right');
            $item->can('view redirects');
        });
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
