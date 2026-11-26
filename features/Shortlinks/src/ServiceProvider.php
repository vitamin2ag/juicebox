<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Shortlinks;

use Override;
use Statamic\CP\Navigation\Nav as Navigation;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Facades\Stache;
use Vitamin2\Juicebox\Features\Shortlinks\Data\Shortlink;
use Vitamin2\Juicebox\Features\Shortlinks\Stache\ShortlinkStore;
use Vitamin2\Juicebox\FeatureServiceProvider;

class ServiceProvider extends FeatureServiceProvider
{
    #[Override]
    public function register(): void
    {
        parent::register();

        if (! config('juicebox.shortlinks.enabled')) {
            return;
        }

        $this->registerStore();
    }

    #[Override]
    protected function bootFeature(): void
    {
        if (! config('juicebox.shortlinks.enabled')) {
            return;
        }

        $this->bootStore();
        $this->bootPermissions();
        $this->bootNavigation();
    }

    #[Override]
    protected function options(): array
    {
        if (! config('juicebox.shortlinks.enabled')) {
            return [];
        }

        return [
            'statamic.vite' => ['input' => 'resources/js/cp.ts', 'publicDirectory' => 'resources', 'buildDirectory' => 'dist'],
            'statamic.serializable_classes' => [Shortlink::class],
        ];
    }

    private function registerStore(): void
    {
        $this->app->singleton(
            ShortlinkStore::class,
            fn (): ShortlinkStore => new ShortlinkStore()
                ->directory(base_path(config()->string('juicebox.shortlinks.store'))),
        );
    }

    private function bootStore(): void
    {
        Stache::registerStore($this->app->make(ShortlinkStore::class));
    }

    private function bootPermissions(): void
    {
        Permission::extend(function (): void {
            Permission::group('shortlinks', 'Shortlinks', function (): void {
                Permission::register('view shortlinks')->label('View Shortlinks')->children([
                    Permission::make('edit shortlinks')->label('Edit Shortlinks')->children([
                        Permission::make('create shortlinks')->label('Create Shortlinks'),
                        Permission::make('delete shortlinks')->label('Delete Shortlinks'),
                    ]),
                ]);
            });
        });
    }

    private function bootNavigation(): void
    {
        Nav::extend(function (Navigation $nav): void {
            $item = $nav->create('Shortlinks');
            $item->section('Tools');
            $item->route('shortlinks.index');
            $item->icon('link');
            $item->can('view shortlinks');
        });
    }
}
