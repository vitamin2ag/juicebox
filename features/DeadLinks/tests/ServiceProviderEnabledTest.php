<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Vitamin2\Juicebox\Features\DeadLinks\ServiceProvider;
use Vitamin2\Juicebox\Features\DeadLinks\Tests\__Fixtures__\DeadLinksFixtures;

covers(ServiceProvider::class);

/*
 * The switch is read while the app boots, so these tests turn it on in beforeAll(), as a project does.
 */
beforeAll(function (): void {
    putenv('JUICEBOX_DEAD_LINKS_ENABLED=true');
});

afterAll(function (): void {
    putenv('JUICEBOX_DEAD_LINKS_ENABLED');
});

it('schedules the check every night at 03:00 without overlapping', function (): void {
    expect(DeadLinksFixtures::scheduledChecks())->toBe([['0 3 * * *', true]]);
});

it('schedules the check at the time schedule sets', function (): void {
    config(['juicebox.dead_links.schedule' => '30 2 * * 1']);
    app()->forgetInstance(Schedule::class);

    expect(DeadLinksFixtures::scheduledChecks())->toBe([['30 2 * * 1', true]]);
});

it('schedules no check when schedule is :dataset', function (mixed $schedule): void {
    config(['juicebox.dead_links.schedule' => $schedule]);
    app()->forgetInstance(Schedule::class);

    expect(DeadLinksFixtures::scheduledChecks())->toBeEmpty();
})->with([
    'false' => false,
    'empty' => '',
    'null' => null,
]);
