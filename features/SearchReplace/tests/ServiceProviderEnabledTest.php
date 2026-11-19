<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
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

it('applies the pending batches every minute without overlapping', function (): void {
    expect(SearchReplaceFixtures::scheduledApplies())->toBe([['* * * * *', true]]);
});

it('applies the pending batches at the time schedule sets', function (): void {
    config(['juicebox.search_replace.schedule' => '*/5 * * * *']);
    app()->forgetInstance(Schedule::class);

    expect(SearchReplaceFixtures::scheduledApplies())->toBe([['*/5 * * * *', true]]);
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
