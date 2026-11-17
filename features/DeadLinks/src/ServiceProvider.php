<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\DeadLinks;

use Illuminate\Console\Scheduling\Schedule;
use Override;
use Vitamin2\Juicebox\Features\DeadLinks\Console\Commands\CheckDeadLinks;
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
}
