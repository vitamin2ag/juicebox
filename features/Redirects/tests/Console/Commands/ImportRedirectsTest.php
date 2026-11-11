<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Statamic\Facades\Site;
use Vitamin2\Juicebox\Features\Redirects\Console\Commands\ImportRedirects;
use Vitamin2\Juicebox\Features\Redirects\Import\ImportResult;
use Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__\RedirectFixtures;

covers(ImportRedirects::class, ImportResult::class);

beforeEach(function (): void {
    RedirectFixtures::isolate();
    Site::setSites([
        'default' => ['url' => '/default/', 'locale' => 'en_US'],
        'de' => ['url' => '/', 'locale' => 'de_CH'],
        'en' => ['url' => '/en/', 'locale' => 'en_US'],
    ]);
});

it('imports the redirects of a CSV file', function (): void {
    $status = Artisan::call('statamic:juicebox:redirects:import', ['file' => RedirectFixtures::csv('invalid.csv')]);

    expect($status)->toBe(0)
        ->and(Artisan::output())->toBe("Imported 1 redirect, skipped 5 rows.\n")
        ->and(RedirectFixtures::sources())->toBe(['de/gueltig']);
});

it('reads a file with the delimiter it gets', function (): void {
    Artisan::call('statamic:juicebox:redirects:import', ['file' => RedirectFixtures::csv('excel.csv'), '--delimiter' => ';']);

    expect(Artisan::output())->toBe("Imported 3 redirects, skipped 0 rows.\n")
        ->and(RedirectFixtures::sources())->toBe(['de/alt', 'de/kurz', 'en/lang']);
});

it('imports nothing while the feature is disabled', function (): void {
    config(['juicebox.redirects.enabled' => false]);

    $status = Artisan::call('statamic:juicebox:redirects:import', ['file' => RedirectFixtures::csv('excel.csv'), '--delimiter' => ';']);

    expect($status)->toBe(1)
        ->and(Artisan::output())->toBe("Redirects are disabled: juicebox.redirects.enabled is false or rias/statamic-redirect is installed.\n")
        ->and(RedirectFixtures::sources())->toBeEmpty();
});

it('fails for a file that does not exist', function (): void {
    $status = Artisan::call('statamic:juicebox:redirects:import', ['file' => 'missing.csv']);

    expect($status)->toBe(1)
        ->and(Artisan::output())->toBe("The file missing.csv does not exist.\n")
        ->and(RedirectFixtures::sources())->toBeEmpty();
});
