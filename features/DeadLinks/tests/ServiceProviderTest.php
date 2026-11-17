<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Vitamin2\Juicebox\Features\DeadLinks\ServiceProvider;

covers(ServiceProvider::class);

afterEach(function (): void {
    putenv('JUICEBOX_DEAD_LINKS_ENABLED');
    putenv('JUICEBOX_DEAD_LINKS_SCHEDULE');
});

it('registers the provider through feature discovery', function (): void {
    expect(app()->getProviders(ServiceProvider::class))->toHaveCount(1);
});

it('disables the feature by default', function (): void {
    expect(config('juicebox.dead_links.enabled'))->toBeFalse();
});

it('merges the config at juicebox.dead_links with its defaults', function (): void {
    expect(config('juicebox.dead_links'))->toBe([
        'enabled' => false,
        'schedule' => '0 3 * * *',
        'timeout' => 10,
        'exclude' => [],
    ]);
});

it('enables the feature through JUICEBOX_DEAD_LINKS_ENABLED', function (): void {
    putenv('JUICEBOX_DEAD_LINKS_ENABLED=true');
    config(['juicebox.dead_links' => require __DIR__.'/../config/dead_links.php']);

    expect(config('juicebox.dead_links.enabled'))->toBeTrue();
});

it('sets the schedule through JUICEBOX_DEAD_LINKS_SCHEDULE', function (string $value, string|false $schedule): void {
    putenv("JUICEBOX_DEAD_LINKS_SCHEDULE=$value");
    config(['juicebox.dead_links' => require __DIR__.'/../config/dead_links.php']);

    expect(config('juicebox.dead_links.schedule'))->toBe($schedule);
})->with([
    'a cron expression' => ['30 2 * * 1', '30 2 * * 1'],
    'false' => ['false', false],
]);

it('publishes the config with the juicebox-dead-links-config tag while the feature is disabled', function (): void {
    $paths = LaravelServiceProvider::pathsToPublish(ServiceProvider::class, 'juicebox-dead-links-config');

    expect(config('juicebox.dead_links.enabled'))->toBeFalse()
        ->and(array_values($paths))->toBe([config_path('juicebox/dead_links.php')]);
});
