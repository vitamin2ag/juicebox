<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Shortlinks;

use Override;
use Statamic\CP\Navigation\Nav as Navigation;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Facades\Stache;
use Vitamin2\Juicebox\Features\Shortlinks\Stache\ShortlinkStore;
use Vitamin2\Juicebox\FeatureServiceProvider;

class ServiceProvider extends FeatureServiceProvider
{
    #[Override]
    public function register(): void
    {
        parent::register();

        $this->app->singleton(ShortlinkStore::class, fn (): ShortlinkStore => new ShortlinkStore()->directory(base_path(config()->string('juicebox.shortlinks.store'))));
    }

    /**
     * The parent offers the config for publishing, which a disabled feature keeps.
     */
    #[Override]
    public function boot(): void
    {
        parent::boot();

        if (! config('juicebox.shortlinks.enabled')) {
            return;
        }

        Stache::registerStore($this->app->make(ShortlinkStore::class));
        Permission::extend($this->registerPermissions(...));
        Nav::extend($this->registerNavigation(...));
    }

    private function registerPermissions(): void
    {
        Permission::group('shortlinks', 'Shortlinks', function (): void {
            Permission::register('view shortlinks')->label('View Shortlinks')->children([
                Permission::make('edit shortlinks')->label('Edit Shortlinks')->children([
                    Permission::make('create shortlinks')->label('Create Shortlinks'),
                    Permission::make('delete shortlinks')->label('Delete Shortlinks'),
                ]),
            ]);
        });
    }

    private function registerNavigation(Navigation $nav): void
    {
        $item = $nav->create('Shortlinks');
        $item->section('Tools');
        $item->route('shortlinks.index');
        $item->icon('link');
        $item->can('view shortlinks');
    }
}
