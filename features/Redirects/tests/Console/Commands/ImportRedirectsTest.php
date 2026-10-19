<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Vitamin2\Juicebox\Features\Redirects\Console\Commands\ImportRedirects;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;
use Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__\RedirectFixtures;

mutates(ImportRedirects::class);

beforeEach(function (): void {
    RedirectFixtures::isolate();
});

/**
 * @return array<int, string>
 */
function importedSources(): array
{
    return resolve(RedirectRepository::class)->all()
        ->map(fn (Redirect $redirect): string => "{$redirect->site()}{$redirect->source()}")
        ->sort()
        ->values()
        ->all();
}

it('imports the redirects of a CSV file', function (): void {
    $status = Artisan::call('statamic:juicebox:redirects:import', ['file' => RedirectFixtures::csv('invalid.csv')]);

    expect($status)->toBe(0)
        ->and(Artisan::output())->toBe("Imported 1 redirect, skipped 5 rows.\n")
        ->and(importedSources())->toBe(['de/gueltig']);
});

it('reads a file with the delimiter it gets', function (): void {
    Artisan::call('statamic:juicebox:redirects:import', ['file' => RedirectFixtures::csv('excel.csv'), '--delimiter' => ';']);

    expect(Artisan::output())->toBe("Imported 3 redirects, skipped 0 rows.\n")
        ->and(importedSources())->toBe(['de/alt', 'default/kurz', 'en/lang']);
});

it('fails for a file that does not exist', function (): void {
    $status = Artisan::call('statamic:juicebox:redirects:import', ['file' => 'missing.csv']);

    expect($status)->toBe(1)
        ->and(Artisan::output())->toBe("The file missing.csv does not exist.\n")
        ->and(importedSources())->toBeEmpty();
});
