<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg;

use Override;
use Statamic\Facades\Fieldset;
use Vitamin2\Juicebox\FeatureServiceProvider;

class ServiceProvider extends FeatureServiceProvider
{
    #[Override]
    public function bootAddon(): void
    {
        Fieldset::addNamespace('schema-org', __DIR__.'/../resources/fieldsets');
    }
}
