<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg;

use Illuminate\Console\Command;
use Override;
use Statamic\Statamic;
use Throwable;
use Vitamin2\Juicebox\FeatureServiceProvider;

class ServiceProvider extends FeatureServiceProvider
{
    #[Override]
    protected function bootFeature(): void
    {
        $this->bootInstall();
    }

    #[Override]
    protected function options(): array
    {
        return [
            'statamic.vite' => ['input' => 'resources/js/cp.ts', 'publicDirectory' => 'resources', 'buildDirectory' => 'dist'],
        ];
    }

    /**
     * Creates the general global and its blueprint tab after `statamic:install`, which composer runs on every
     * install and update. Only the local environment writes them: a deployed checkout gets both files from git.
     * A failure only warns, so it cannot break the composer run.
     */
    private function bootInstall(): void
    {
        if (! $this->app->isLocal()) {
            return;
        }

        Statamic::afterInstalled(function (Command $command): void {
            if (! config('juicebox.schema_org.enabled')) {
                return;
            }

            try {
                $command->call('statamic:juicebox:schema-org:install');
            } catch (Throwable $exception) {
                $command->warn(__('Could not install the Schema.org global: :message', ['message' => $exception->getMessage()]));
            }
        });
    }
}
