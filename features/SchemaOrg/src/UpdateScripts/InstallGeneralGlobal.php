<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\UpdateScripts;

use Illuminate\Support\Facades\Artisan;
use Override;
use Statamic\UpdateScripts\UpdateScript;

class InstallGeneralGlobal extends UpdateScript
{
    #[Override]
    public function shouldUpdate($newVersion, $oldVersion): bool
    {
        return config()->boolean('juicebox.schema_org.enabled');
    }

    #[Override]
    public function update(): void
    {
        Artisan::call('statamic:juicebox:schema-org:install');
    }
}
