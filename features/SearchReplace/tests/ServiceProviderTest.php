<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Vitamin2\Juicebox\Features\SearchReplace\ServiceProvider;
use Vitamin2\Juicebox\Features\SearchReplace\Tests\__Fixtures__\SearchReplaceFixtures;

covers(ServiceProvider::class);

afterEach(function (): void {
    putenv('JUICEBOX_SEARCH_REPLACE_ENABLED');
    putenv('JUICEBOX_SEARCH_REPLACE_SCHEDULE');
    putenv('JUICEBOX_SEARCH_REPLACE_KEEP_DAYS');
});

it('registers the provider through feature discovery', function (): void {
    expect(app()->getProviders(ServiceProvider::class))->toHaveCount(1);
});

it('disables the feature by default', function (): void {
    expect(config('juicebox.search_replace.enabled'))->toBeFalse();
});

it('merges the config at juicebox.search_replace with its defaults', function (): void {
    expect(config('juicebox.search_replace'))->toBe([
        'enabled' => false,
        'schedule' => '* * * * *',
        'keep_days' => 30,
    ]);
});

it('enables the feature through JUICEBOX_SEARCH_REPLACE_ENABLED', function (): void {
    putenv('JUICEBOX_SEARCH_REPLACE_ENABLED=true');
    config(['juicebox.search_replace' => require __DIR__.'/../config/search_replace.php']);

    expect(config('juicebox.search_replace.enabled'))->toBeTrue();
});

it('sets the schedule through JUICEBOX_SEARCH_REPLACE_SCHEDULE', function (string $value, string|false $schedule): void {
    putenv("JUICEBOX_SEARCH_REPLACE_SCHEDULE=$value");
    config(['juicebox.search_replace' => require __DIR__.'/../config/search_replace.php']);

    expect(config('juicebox.search_replace.schedule'))->toBe($schedule);
})->with([
    'a cron expression' => ['*/5 * * * *', '*/5 * * * *'],
    'false' => ['false', false],
]);

it('sets the days batches are kept through JUICEBOX_SEARCH_REPLACE_KEEP_DAYS', function (string $value, string|false $days): void {
    putenv("JUICEBOX_SEARCH_REPLACE_KEEP_DAYS=$value");
    config(['juicebox.search_replace' => require __DIR__.'/../config/search_replace.php']);

    expect(config('juicebox.search_replace.keep_days'))->toBe($days);
})->with([
    'a number of days' => ['90', '90'],
    'false' => ['false', false],
]);

it('schedules nothing while the feature is disabled', function (): void {
    expect(SearchReplaceFixtures::scheduledApplies())->toBeEmpty();
});

it('leaves the Search & Replace utility and its route out of the control panel while the feature is disabled', function (string $route): void {
    expect(Route::has($route))->toBeFalse();
})->with([
    'page' => 'statamic.cp.utilities.search-replace',
    'replacement' => 'statamic.cp.utilities.search-replace.replace',
    'undo' => 'statamic.cp.utilities.search-replace.undo',
]);

/*
 * Statamic keeps the builds it knows in a static list, which a test that boots the feature enabled leaves behind,
 * so this test asks the provider for its options.
 */
it('registers no control panel build while the feature is disabled', function (): void {
    $options = new ReflectionMethod(ServiceProvider::class, 'options')->invoke(app()->getProvider(ServiceProvider::class));

    expect($options)->toBe([]);
});

it('publishes the config with the juicebox-search-replace-config tag while the feature is disabled', function (): void {
    $paths = LaravelServiceProvider::pathsToPublish(ServiceProvider::class, 'juicebox-search-replace-config');

    expect(config('juicebox.search_replace.enabled'))->toBeFalse()
        ->and(array_values($paths))->toBe([config_path('juicebox/search_replace.php')]);
});
