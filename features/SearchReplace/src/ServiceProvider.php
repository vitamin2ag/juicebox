<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace;

use Illuminate\Console\Scheduling\Schedule;
use Override;
use Vitamin2\Juicebox\Features\SearchReplace\Console\Commands\ApplyReplacements;
use Vitamin2\Juicebox\FeatureServiceProvider;

class ServiceProvider extends FeatureServiceProvider
{
    #[Override]
    protected function bootFeature(): void
    {
        if (! config('juicebox.search_replace.enabled')) {
            return;
        }

        $this->bootSchedule();
    }

    /**
     * Any schedule that is not a cron expression, such as false from the env, schedules nothing.
     */
    private function bootSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $expression = config('juicebox.search_replace.schedule');

            if (! is_string($expression) || $expression === '') {
                return;
            }

            $schedule->command(ApplyReplacements::class)->cron($expression)->withoutOverlapping();
        });
    }
}
