<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Site;
use Vitamin2\Juicebox\Features\DeadLinks\Console\Commands\CheckDeadLinks;
use Vitamin2\Juicebox\Features\DeadLinks\Tests\__Fixtures__\DeadLinksFixtures;

covers(CheckDeadLinks::class);

beforeEach(function (): void {
    DeadLinksFixtures::sites();
    config(['juicebox.dead_links.enabled' => true]);
    Storage::fake('local');
    Http::preventStrayRequests();
    $this->travelTo(CarbonImmutable::parse('2026-11-17 03:00:00'));
});

/**
 * The report the check saved, as its JSON holds it.
 */
function savedDeadLinksReport(): mixed
{
    return json_decode(Storage::disk('local')->get('juicebox/dead-links.json') ?? '', true);
}

it('checks every link of the content once and saves the broken ones with their sources', function (): void {
    DeadLinksFixtures::entry('kontakt', ['title' => 'Kontakt']);
    DeadLinksFixtures::entry('ueber-uns', [
        'title' => 'Über uns',
        'link' => 'entry::kontakt',
        'partner' => 'https://example.com/partner',
        'body' => 'See [the archive](2024) or https://example.com/alt.',
    ]);
    DeadLinksFixtures::globalSet('footer', ['de' => ['imprint' => 'entry::impressum', 'archive' => 'https://example.com/alt']]);
    Http::fake([
        'https://example.com/partner' => Http::response(),
        'https://example.com/alt' => Http::response(status: 404),
    ]);

    $status = Artisan::call('statamic:juicebox:dead-links:check');

    expect($status)->toBe(0)
        ->and(Artisan::output())->toBe("Checked 5 links, found 3 broken links.\n")
        ->and(savedDeadLinksReport())->toBe([
            'checked_at' => '2026-11-17T03:00:00+00:00',
            'checked' => 5,
            'links' => [
                [
                    'url' => '2024',
                    'reason' => 'invalid_url',
                    'status' => null,
                    'sources' => [['type' => 'entry', 'id' => 'ueber-uns', 'site' => 'de', 'field' => 'body']],
                ],
                [
                    'url' => 'https://example.com/alt',
                    'reason' => 'page_not_found',
                    'status' => 404,
                    'sources' => [
                        ['type' => 'entry', 'id' => 'ueber-uns', 'site' => 'de', 'field' => 'body'],
                        ['type' => 'global', 'id' => 'footer', 'site' => 'de', 'field' => 'archive'],
                    ],
                ],
                [
                    'url' => 'entry::impressum',
                    'reason' => 'missing_entry',
                    'status' => null,
                    'sources' => [['type' => 'global', 'id' => 'footer', 'site' => 'de', 'field' => 'imprint']],
                ],
            ],
        ]);

    Http::assertSentCount(3);
});

it('checks a path on the domain of the site that holds it', function (): void {
    Site::setSites([
        'de' => ['url' => 'https://example.ch/', 'locale' => 'de_CH'],
        'en' => ['url' => 'https://example.com/', 'locale' => 'en_US'],
    ]);
    DeadLinksFixtures::entry('kontakt', ['title' => 'Kontakt', 'prices' => '/preise.pdf']);
    DeadLinksFixtures::entry('contact', ['title' => 'Contact', 'prices' => '/preise.pdf'], 'en', 'kontakt');
    Http::fake([
        'https://example.ch/preise.pdf' => Http::response(),
        'https://example.com/preise.pdf' => Http::response(status: 404),
    ]);

    Artisan::call('statamic:juicebox:dead-links:check');

    expect(Artisan::output())->toBe("Checked 2 links, found 1 broken link.\n")
        ->and(savedDeadLinksReport())->toBe([
            'checked_at' => '2026-11-17T03:00:00+00:00',
            'checked' => 2,
            'links' => [
                [
                    'url' => 'https://example.com/preise.pdf',
                    'reason' => 'page_not_found',
                    'status' => 404,
                    'sources' => [['type' => 'entry', 'id' => 'contact', 'site' => 'en', 'field' => 'prices']],
                ],
            ],
        ]);

    Http::assertSentCount(3);
});

it('saves a report without links when no link is broken', function (): void {
    DeadLinksFixtures::entry('kontakt', ['title' => 'Kontakt', 'self' => 'entry::kontakt']);

    $status = Artisan::call('statamic:juicebox:dead-links:check');

    expect($status)->toBe(0)
        ->and(Artisan::output())->toBe("Checked 1 link, found 0 broken links.\n")
        ->and(savedDeadLinksReport())->toBe(['checked_at' => '2026-11-17T03:00:00+00:00', 'checked' => 1, 'links' => []]);
});

it('checks nothing while the feature is disabled', function (): void {
    DeadLinksFixtures::entry('ueber-uns', ['title' => 'Über uns', 'partner' => 'https://example.com/partner']);
    config(['juicebox.dead_links.enabled' => false]);
    Http::fake();

    $status = Artisan::call('statamic:juicebox:dead-links:check');

    expect($status)->toBe(1)
        ->and(Artisan::output())->toBe("The dead link check is disabled: juicebox.dead_links.enabled is false.\n")
        ->and(Storage::disk('local')->exists('juicebox/dead-links.json'))->toBeFalse();

    Http::assertNothingSent();
});
