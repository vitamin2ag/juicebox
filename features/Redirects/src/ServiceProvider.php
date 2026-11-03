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
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
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

        $this->app->singleton(RedirectStore::class, fn (): RedirectStore => new RedirectStore()->directory(base_path(config()->string('juicebox.redirects.store'))));
    }

    /**
     * rias/statamic-redirect registers the same store and handles the same requests, so the
     * feature switches itself off until rias is removed. The routes, listeners and commands
     * boot either way and check the same switch.
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
        $this->registerSerializableClasses();
        $this->registerFilters();
        $this->registerPermissions();
        $this->registerNavigation();
        $this->registerMiddleware();
    }

    /**
     * The root .gitignore ignores every build folder, so the control panel build goes to dist.
     *
     * @return array<string, mixed>
     */
    #[Override]
    protected function options(): array
    {
        return ['statamic.vite' => ['input' => 'resources/js/cp.ts', 'publicDirectory' => 'resources', 'buildDirectory' => 'dist']];
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

    /**
     * The names are those of rias/statamic-redirect, so the roles that have them keep working.
     */
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

    /**
     * The Stache caches every redirect as a serialized object, which Laravel only unserializes when
     * cache.serializable_classes lists its class. Statamic's AddonServiceProvider does the same for
     * an addon, but a feature cannot call it.
     */
    private function registerSerializableClasses(): void
    {
        $existing = config('cache.serializable_classes');

        if ($existing === null || $existing === true) {
            return;
        }

        config(['cache.serializable_classes' => [...(is_array($existing) ? $existing : []), Redirect::class]]);
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
