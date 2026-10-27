<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Shortlinks;

use Override;
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
    }
}
