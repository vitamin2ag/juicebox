<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox;

use Edalzell\Features\Concerns\HasFeatures;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Override;

class ServiceProvider extends LaravelServiceProvider
{
    use HasFeatures;

    #[Override]
    public function register(): void
    {
        $this->registerFeatures();
    }
}
