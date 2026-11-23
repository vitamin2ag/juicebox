<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Override;
use Statamic\Facades\Utility;
use Statamic\Http\Middleware\CP\TrimStrings;
use Vitamin2\Juicebox\Features\SearchReplace\Console\Commands\ApplyReplacements;
use Vitamin2\Juicebox\Features\SearchReplace\Http\Controllers\ReplaceController;
use Vitamin2\Juicebox\Features\SearchReplace\Http\Controllers\UndoController;
use Vitamin2\Juicebox\Features\SearchReplace\Http\SearchReplacePage;
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
        $this->bootUtility();
    }

    #[Override]
    protected function options(): array
    {
        if (! config('juicebox.search_replace.enabled')) {
            return [];
        }

        return [
            'statamic.vite' => ['input' => 'resources/js/cp.ts', 'publicDirectory' => 'resources', 'buildDirectory' => 'dist'],
        ];
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

    /**
     * Statamic grants the permission `access search_replace utility` for it and requires it on its routes too. The
     * control panel trims every input, which would drop the spaces a search or replacement starts or ends with.
     */
    private function bootUtility(): void
    {
        Utility::extend(function (): void {
            $utility = Utility::register('search_replace');
            $utility->title(__('Search & Replace'));
            $utility->description(__('Finds a term in the text of entries, terms and globals and replaces the selected matches.'));
            $utility->icon('replace');
            $utility->inertia('search-replace::Index', fn (Request $request): array => resolve(SearchReplacePage::class)->toArray($request));
            $utility->routes(function (Router $router): void {
                $router->post('replace', ReplaceController::class)
                    ->name('replace')
                    ->withoutMiddleware(TrimStrings::class);
                $router->post('undo', UndoController::class)->name('undo');
            });
        });
    }
}
