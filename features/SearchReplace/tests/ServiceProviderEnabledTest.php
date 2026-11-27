<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Inertia\Testing\AssertableInertia;
use Statamic\Statamic;
use Vitamin2\Juicebox\Features\SearchReplace\ServiceProvider;
use Vitamin2\Juicebox\Features\SearchReplace\Tests\__Fixtures__\SearchReplaceFixtures;

covers(ServiceProvider::class);

/*
 * The switch is read while the app boots, so these tests turn it on in beforeAll(), as a project does.
 */
beforeAll(function (): void {
    putenv('JUICEBOX_SEARCH_REPLACE_ENABLED=true');
});

afterAll(function (): void {
    putenv('JUICEBOX_SEARCH_REPLACE_ENABLED');
});

it('applies the pending batches every minute, without the overlap lock of the scheduler, which a dead run would hold for a day', function (): void {
    expect(SearchReplaceFixtures::scheduledApplies())->toBe([['* * * * *', false]]);
});

it('applies the pending batches at the time schedule sets', function (): void {
    config(['juicebox.search_replace.schedule' => '*/5 * * * *']);
    app()->forgetInstance(Schedule::class);

    expect(SearchReplaceFixtures::scheduledApplies())->toBe([['*/5 * * * *', false]]);
});

it('lists the Search & Replace utility in the control panel', function (): void {
    $this->actingAs(SearchReplaceFixtures::superUser())
        ->get(SearchReplaceFixtures::cpUrl('utilities.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('utilities', fn (Collection $utilities): bool => $utilities->firstWhere('title', 'Search & Replace') === [
                'title' => 'Search & Replace',
                'description' => 'Finds a term in the text of entries, terms and globals and replaces the selected matches.',
                'icon' => Statamic::svg('icons/replace'),
                'url' => SearchReplaceFixtures::cpUrl('utilities.search-replace'),
            ]));
});

it('schedules nothing when schedule is :dataset', function (mixed $schedule): void {
    config(['juicebox.search_replace.schedule' => $schedule]);
    app()->forgetInstance(Schedule::class);

    expect(SearchReplaceFixtures::scheduledApplies())->toBeEmpty();
})->with([
    'false' => false,
    'empty' => '',
    'null' => null,
]);

it('registers the control panel build with Vite', function (): void {
    $feature = dirname(__DIR__);

    expect(Statamic::availableVites(request()))->toHaveKey('search-replace', [
        'hotFile' => "{$feature}/resources/hot",
        'buildDirectory' => 'vendor/search-replace/dist',
        'input' => 'resources/js/cp.ts',
    ]);
});

it('publishes the control panel build with the search-replace tag', function (): void {
    $feature = dirname(__DIR__);
    $paths = LaravelServiceProvider::pathsToPublish(ServiceProvider::class, 'search-replace');

    expect($paths)->toBe(["{$feature}/resources/dist/" => public_path('vendor/search-replace/dist/')]);
});
