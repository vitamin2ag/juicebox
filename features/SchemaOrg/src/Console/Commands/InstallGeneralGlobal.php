<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Console\Commands;

use Illuminate\Console\Command;
use Override;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\Blueprint;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Fields\Blueprint as StatamicBlueprint;
use Statamic\Globals\GlobalSet as StatamicGlobalSet;

class InstallGeneralGlobal extends Command
{
    use RunsInPlease;

    #[Override]
    protected $signature = 'statamic:juicebox:schema-org:install';

    #[Override]
    protected $description = 'Ensure the "general" global and its JSON-LD blueprint tab exist';

    public function handle(): void
    {
        $this->ensureGlobalSet();
        $this->ensureBlueprintTab();
    }

    protected function ensureGlobalSet(): void
    {
        if (GlobalSet::find('general')) {
            return;
        }

        $set = new StatamicGlobalSet;
        $set->handle('general');
        $set->sites(Site::all()->keys()->all());
        $set->save();

        $this->components->info(__('Created the [general] global.'));
    }

    protected function ensureBlueprintTab(): void
    {
        $tab = [
            'display' => 'JSON-LD',
            'sections' => [
                [
                    'fields' => [
                        ['import' => 'schema-org::globals_json_ld'],
                    ],
                ],
            ],
        ];

        $blueprint = Blueprint::find('globals.general');

        if ($blueprint?->hasTab('json_ld')) {
            return;
        }

        if ($blueprint) {
            $blueprint->setContents([...$blueprint->contents(), 'tabs' => [...$this->tabsOf($blueprint), 'json_ld' => $tab]]);
            $blueprint->save();

            $this->components->info(__('Added the [json_ld] tab to the [globals.general] blueprint.'));

            return;
        }

        $blueprint = new StatamicBlueprint;
        $blueprint->setHandle('general');
        $blueprint->setNamespace('globals');
        $blueprint->setContents(['tabs' => ['json_ld' => $tab]]);
        $blueprint->save();

        $this->components->info(__('Created the [globals.general] blueprint.'));
    }

    /** @return array<array-key, mixed> */
    private function tabsOf(StatamicBlueprint $blueprint): array
    {
        $tabs = $blueprint->contents()['tabs'] ?? [];

        return is_array($tabs) ? $tabs : [];
    }
}
