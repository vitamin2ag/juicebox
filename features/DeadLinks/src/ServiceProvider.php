<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\DeadLinks;

use Illuminate\Console\Scheduling\Schedule;
use Override;
use Statamic\Facades\Utility;
use Vitamin2\Juicebox\Features\DeadLinks\Console\Commands\CheckDeadLinks;
use Vitamin2\Juicebox\Features\DeadLinks\Http\DeadLinksPage;
use Vitamin2\Juicebox\FeatureServiceProvider;

class ServiceProvider extends FeatureServiceProvider
{
    #[Override]
    protected function bootFeature(): void
    {
        if (! config('juicebox.dead_links.enabled')) {
            return;
        }

        $this->bootSchedule();
        $this->bootUtility();
    }

    #[Override]
    protected function options(): array
    {
        if (! config('juicebox.dead_links.enabled')) {
            return [];
        }

        return [
            'statamic.vite' => ['input' => 'resources/js/cp.ts', 'publicDirectory' => 'resources', 'buildDirectory' => 'dist'],
        ];
    }

    /**
     * Any schedule that is not a cron expression, such as false from the env, schedules no check.
     */
    private function bootSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $expression = config('juicebox.dead_links.schedule');

            if (! is_string($expression) || $expression === '') {
                return;
            }

            $schedule->command(CheckDeadLinks::class)->cron($expression)->withoutOverlapping();
        });
    }

    /**
     * Statamic grants the permission `access dead_links utility` for it.
     */
    private function bootUtility(): void
    {
        Utility::extend(function (): void {
            $utility = Utility::register('dead_links');
            $utility->title(__('Dead Links'));
            $utility->description(__('Lists the broken links that the last check found in the content.'));
            $utility->icon('link');
            $utility->inertia('dead-links::Index', fn (): array => resolve(DeadLinksPage::class)->toArray());
        });
    }
}
