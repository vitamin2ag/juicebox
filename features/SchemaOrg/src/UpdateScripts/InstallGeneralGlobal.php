<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\UpdateScripts;

use Illuminate\Support\Facades\Artisan;
use Statamic\UpdateScripts\UpdateScript;

class InstallGeneralGlobal extends UpdateScript
{
    public function shouldUpdate($newVersion, $oldVersion): bool
    {
        return true;
    }

    public function update(): void
    {
        Artisan::call('statamic:juicebox:schema-org:install');
    }
}
