<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;
use Statamic\Statamic;
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

it('lists the Dead Links utility in the control panel', function (): void {
    $this->actingAs(DeadLinksFixtures::superUser())
        ->get(DeadLinksFixtures::cpUrl('utilities.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('utilities', fn (Collection $utilities): bool => $utilities->firstWhere('title', 'Dead Links') === [
                'title' => 'Dead Links',
                'description' => 'Lists the broken links that the last check found in the content.',
                'icon' => Statamic::svg('icons/link'),
                'url' => DeadLinksFixtures::cpUrl('utilities.dead-links'),
            ]));
});
