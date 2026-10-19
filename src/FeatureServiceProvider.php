<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox;

use Edalzell\Features\Providers\FeatureServiceProvider as BaseFeatureServiceProvider;
use Illuminate\Support\Str;
use Override;

abstract class FeatureServiceProvider extends BaseFeatureServiceProvider
{
    #[Override]
    protected function configFileName(): string
    {
        return Str::snake($this->name());
    }

    #[Override]
    protected function configPublishHandle(): string
    {
        return 'juicebox-'.$this->slug();
    }
}
