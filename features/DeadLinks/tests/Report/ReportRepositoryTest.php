<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Vitamin2\Juicebox\Features\DeadLinks\Checking\Reason;
use Vitamin2\Juicebox\Features\DeadLinks\Data\Source;
use Vitamin2\Juicebox\Features\DeadLinks\Report\DeadLink;
use Vitamin2\Juicebox\Features\DeadLinks\Report\Report;
use Vitamin2\Juicebox\Features\DeadLinks\Report\ReportRepository;

covers(ReportRepository::class, Report::class, DeadLink::class);

beforeEach(function (): void {
    Storage::fake('local');
});

/**
 * The JSON of a report with one dead link, with the value at the given path replaced.
 */
function deadLinksReportJson(?string $path = null, mixed $value = null): string
{
    $report = [
        'checked_at' => '2026-11-17T03:00:00+00:00',
        'checked' => 4,
        'links' => [[
            'url' => 'entry::alt',
            'reason' => 'missing_entry',
            'status' => null,
            'sources' => [['type' => 'entry', 'id' => 'ueber-uns', 'site' => 'de', 'field' => 'link']],
        ]],
    ];

    if ($path !== null) {
        Arr::set($report, $path, $value);
    }

    return json_encode($report, JSON_THROW_ON_ERROR);
}

it('saves the report as JSON in juicebox/dead-links.json on the local disk', function (): void {
    resolve(ReportRepository::class)->save(new Report(CarbonImmutable::parse('2026-11-17 03:00:00'), 12, [
        new DeadLink('https://example.com/über/', Reason::PageNotFound, 404, [new Source('entry', 'ueber-uns', 'de', 'body')]),
    ]));

    expect(Storage::disk('local')->get('juicebox/dead-links.json'))->toBe(<<<'JSON'
        {
            "checked_at": "2026-11-17T03:00:00+00:00",
            "checked": 12,
            "links": [
                {
                    "url": "https://example.com/über/",
                    "reason": "page_not_found",
                    "status": 404,
                    "sources": [
                        {
                            "type": "entry",
                            "id": "ueber-uns",
                            "site": "de",
                            "field": "body"
                        }
                    ]
                }
            ]
        }
        JSON);
});

it('converts the report to the values of its JSON', function (): void {
    $report = new Report(CarbonImmutable::parse('2026-11-17 03:00:00'), 4, [
        new DeadLink('entry::alt', Reason::MissingEntry, null, [new Source('entry', 'ueber-uns', 'de', 'link')]),
    ]);

    expect($report->toArray())->toBe(json_decode(deadLinksReportJson(), true));
});

it('finds the saved report', function (): void {
    $report = new Report(CarbonImmutable::parse('2026-11-17 03:00:00'), 7, [
        new DeadLink('entry::impressum', Reason::MissingEntry, null, [
            new Source('global', 'footer', 'de', 'imprint'),
            new Source('nav', 'main', 'en', 'entry'),
        ]),
        new DeadLink('2024', Reason::InvalidUrl, null, [new Source('term', 'tags::news', 'de', 'description')]),
        new DeadLink('https://example.com/alt', Reason::HttpError, 500, [new Source('entry', 'ueber-uns', 'de', 'body')]),
    ]);
    $reports = resolve(ReportRepository::class);

    $reports->save($report);

    expect($reports->find())->toEqual($report)
        ->and($reports->find()?->links[1]->url)->toBe('2024');
});

it('reads the report a file holds', function (): void {
    Storage::disk('local')->put('juicebox/dead-links.json', deadLinksReportJson());

    expect(resolve(ReportRepository::class)->find())->toEqual(new Report(CarbonImmutable::parse('2026-11-17 03:00:00'), 4, [
        new DeadLink('entry::alt', Reason::MissingEntry, null, [new Source('entry', 'ueber-uns', 'de', 'link')]),
    ]));
});

it('overwrites the report of the last check', function (): void {
    $reports = resolve(ReportRepository::class);
    $latest = new Report(CarbonImmutable::parse('2026-11-17 03:00:00'), 2, []);

    $reports->save(new Report(CarbonImmutable::parse('2026-11-16 03:00:00'), 3, [
        new DeadLink('entry::alt', Reason::MissingEntry, null, [new Source('entry', 'ueber-uns', 'de', 'link')]),
    ]));
    $reports->save($latest);

    expect($reports->find())->toEqual($latest)
        ->and(Storage::disk('local')->allFiles())->toBe(['juicebox/dead-links.json']);
});

it('finds no report before the first check', function (): void {
    expect(resolve(ReportRepository::class)->find())->toBeNull();
});

it('treats a file :dataset as missing', function (string $contents): void {
    Storage::disk('local')->put('juicebox/dead-links.json', $contents);

    expect(resolve(ReportRepository::class)->find())->toBeNull();
})->with([
    'that is no JSON' => '{"checked_at":',
    'that holds no object' => '"report"',
    'without checked_at' => deadLinksReportJson('checked_at'),
    'with a checked_at that is no date' => deadLinksReportJson('checked_at', 'gestern'),
    'with a count that is no number' => deadLinksReportJson('checked', '4'),
    'with links that are no list' => deadLinksReportJson('links', 'entry::alt'),
    'with a link that is no object' => deadLinksReportJson('links.0', 'entry::alt'),
    'with a link without url' => deadLinksReportJson('links.0.url'),
    'with a link of an unknown reason' => deadLinksReportJson('links.0.reason', 'gone'),
    'with a status that is no number' => deadLinksReportJson('links.0.status', '404'),
    'with sources that are no list' => deadLinksReportJson('links.0.sources', 'entry'),
    'with a source that is no object' => deadLinksReportJson('links.0.sources.0', 'entry'),
    'with a source of an unknown type' => deadLinksReportJson('links.0.sources.0.type', 'user'),
    'with a source without id' => deadLinksReportJson('links.0.sources.0.id'),
    'with a source without site' => deadLinksReportJson('links.0.sources.0.site'),
    'with a source without field' => deadLinksReportJson('links.0.sources.0.field'),
]);
