<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects;

use Override;
use Statamic\Facades\Addon;
use Statamic\Facades\Stache;
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
     * registers the same store and handles the same requests.
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
    }

    private function registerStore(): void
    {
        Stache::registerStore($this->app->make(RedirectStore::class));
    }
}
