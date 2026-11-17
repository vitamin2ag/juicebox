<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Statamic\Facades\Site;
use Vitamin2\Juicebox\Features\DeadLinks\Checking\Reason;
use Vitamin2\Juicebox\Features\DeadLinks\Data\Source;
use Vitamin2\Juicebox\Features\DeadLinks\Http\DeadLinksPage;
use Vitamin2\Juicebox\Features\DeadLinks\Report\DeadLink;
use Vitamin2\Juicebox\Features\DeadLinks\Report\Report;
use Vitamin2\Juicebox\Features\DeadLinks\Report\ReportRepository;
use Vitamin2\Juicebox\Features\DeadLinks\ServiceProvider;
use Vitamin2\Juicebox\Features\DeadLinks\Tests\__Fixtures__\DeadLinksFixtures;

covers(DeadLinksPage::class, ServiceProvider::class);

/*
 * The switch is read while the app boots, so these tests turn it on in beforeAll(), as a project does.
 */
beforeAll(function (): void {
    putenv('JUICEBOX_DEAD_LINKS_ENABLED=true');
});

afterAll(function (): void {
    putenv('JUICEBOX_DEAD_LINKS_ENABLED');
});

beforeEach(function (): void {
    Storage::fake('local');
    config(['statamic.editions.pro' => true]);
    DeadLinksFixtures::sites();
    DeadLinksFixtures::blueprints();
});

function saveDeadLinksReport(DeadLink ...$links): void
{
    resolve(ReportRepository::class)->save(new Report(CarbonImmutable::parse('2026-11-17T03:00:00+00:00'), 12, array_values($links)));
}

/**
 * @return array<int, array{type: string, title: string, editUrl: string|null, site: string|null, field: string, missing: bool}>
 */
function deadLinkSources(Source ...$sources): array
{
    saveDeadLinksReport(new DeadLink('https://example.com/alt', Reason::PageNotFound, 404, array_values($sources)));

    return resolve(DeadLinksPage::class)->toArray()['links'][0]['sources'];
}

it('opens the utility with the report of the last check', function (): void {
    DeadLinksFixtures::entry('kontakt', ['title' => 'Kontakt']);
    DeadLinksFixtures::term('news', ['de' => ['title' => 'News']]);
    DeadLinksFixtures::globalSet('footer', ['de' => []]);
    DeadLinksFixtures::nav('main', ['en' => []]);
    saveDeadLinksReport(
        new DeadLink('https://example.com/alt', Reason::PageNotFound, 404, [
            new Source('entry', 'kontakt', 'de', 'link'),
            new Source('term', 'tags::news', 'de', 'body'),
        ]),
        new DeadLink('entry::weg', Reason::MissingEntry, null, [
            new Source('global', 'footer', 'de', 'social'),
            new Source('nav', 'main', 'en', 'entry'),
        ]),
    );

    $this->actingAs(DeadLinksFixtures::superUser())
        ->get(DeadLinksFixtures::cpUrl('utilities.dead-links'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('dead-links::Index')
            ->where('checkedAt', '2026-11-17T03:00:00+00:00')
            ->where('checked', 12)
            ->where('links', [
                [
                    'url' => 'https://example.com/alt',
                    'reason' => 'page_not_found',
                    'reasonLabel' => 'Page not found (404)',
                    'status' => 404,
                    'sources' => [
                        [
                            'type' => 'entry',
                            'title' => 'Kontakt',
                            'editUrl' => DeadLinksFixtures::cpUrl('collections.entries.edit', ['pages', 'kontakt']),
                            'site' => 'Deutsch',
                            'field' => 'Weiterführender Link',
                            'missing' => false,
                        ],
                        [
                            'type' => 'term',
                            'title' => 'News',
                            'editUrl' => DeadLinksFixtures::cpUrl('taxonomies.terms.edit', ['tags', 'news', 'de']),
                            'site' => 'Deutsch',
                            'field' => 'Beschreibung',
                            'missing' => false,
                        ],
                    ],
                ],
                [
                    'url' => 'entry::weg',
                    'reason' => 'missing_entry',
                    'reasonLabel' => 'Entry not found',
                    'status' => null,
                    'sources' => [
                        [
                            'type' => 'global',
                            'title' => 'Footer',
                            'editUrl' => DeadLinksFixtures::cpUrl('globals.variables.edit', ['footer', 'site' => 'de']),
                            'site' => 'Deutsch',
                            'field' => 'Social Media',
                            'missing' => false,
                        ],
                        [
                            'type' => 'nav',
                            'title' => 'Main',
                            'editUrl' => DeadLinksFixtures::cpUrl('navigation.show', ['navigation' => 'main', 'site' => 'en']),
                            'site' => 'English',
                            'field' => 'Entry',
                            'missing' => false,
                        ],
                    ],
                ],
            ]));
});

it('tells the page that no check has run yet', function (): void {
    $this->actingAs(DeadLinksFixtures::superUser())
        ->get(DeadLinksFixtures::cpUrl('utilities.dead-links'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('dead-links::Index')
            ->where('checkedAt', null)
            ->where('checked', 0)
            ->where('links', []));
});

it('opens the utility for a user with the access dead_links utility permission', function (): void {
    $this->actingAs(DeadLinksFixtures::userWith('access dead_links utility'))
        ->get(DeadLinksFixtures::cpUrl('utilities.dead-links'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('dead-links::Index'));
});

it('forbids the utility without the access dead_links utility permission', function (): void {
    $this->actingAs(DeadLinksFixtures::userWith())
        ->getJson(DeadLinksFixtures::cpUrl('utilities.dead-links'))
        ->assertForbidden();
});

it('labels :dataset', function (Reason $reason, ?int $status, string $label): void {
    saveDeadLinksReport(new DeadLink('https://example.com/alt', $reason, $status, []));

    expect(resolve(DeadLinksPage::class)->toArray()['links'][0]['reasonLabel'])->toBe($label);
})->with([
    'a missing entry' => [Reason::MissingEntry, null, 'Entry not found'],
    'an unpublished entry' => [Reason::UnpublishedEntry, null, 'Entry not published'],
    'a missing asset' => [Reason::MissingAsset, null, 'Asset not found'],
    'a page that is gone' => [Reason::PageNotFound, 410, 'Page not found (410)'],
    'an HTTP error with its status' => [Reason::HttpError, 500, 'HTTP error (500)'],
    'a failed connection' => [Reason::ConnectionFailed, null, 'Connection failed'],
    'an invalid URL' => [Reason::InvalidUrl, null, 'Invalid URL'],
]);

it('marks the sources that no longer exist as missing', function (): void {
    DeadLinksFixtures::globalSet('footer', ['de' => []]);

    expect(deadLinkSources(
        new Source('entry', 'geloescht', 'de', 'link'),
        new Source('term', 'tags::alt', 'de', 'body'),
        new Source('global', 'header', 'de', 'logo'),
        new Source('global', 'footer', 'en', 'social'),
        new Source('nav', 'footer', 'en', 'url'),
    ))->toBe([
        ['type' => 'entry', 'title' => 'Missing entry', 'editUrl' => null, 'site' => 'Deutsch', 'field' => 'link', 'missing' => true],
        ['type' => 'term', 'title' => 'Missing term', 'editUrl' => null, 'site' => 'Deutsch', 'field' => 'body', 'missing' => true],
        ['type' => 'global', 'title' => 'Missing global set', 'editUrl' => null, 'site' => 'Deutsch', 'field' => 'logo', 'missing' => true],
        ['type' => 'global', 'title' => 'Missing global set', 'editUrl' => null, 'site' => 'English', 'field' => 'social', 'missing' => true],
        ['type' => 'nav', 'title' => 'Missing navigation', 'editUrl' => null, 'site' => 'English', 'field' => 'url', 'missing' => true],
    ]);
});

it('titles an entry without a title by its id and a field the blueprint lacks by its handle', function (): void {
    DeadLinksFixtures::entry('ohne-titel', []);

    expect(deadLinkSources(new Source('entry', 'ohne-titel', 'de', 'teaser'))[0])->toMatchArray(['title' => 'ohne-titel', 'field' => 'teaser']);
});

it('names the field by its handle when the blueprint of a term no longer exists', function (): void {
    DeadLinksFixtures::term('news', ['de' => ['title' => 'News', 'blueprint' => 'geloescht']]);

    expect(deadLinkSources(new Source('term', 'tags::news', 'de', 'body'))[0]['field'])->toBe('body');
});

it('names a navigation field that links to a URL', function (): void {
    DeadLinksFixtures::nav('main', ['de' => []]);

    expect(deadLinkSources(new Source('nav', 'main', 'de', 'url'))[0]['field'])->toBe('URL');
});

it('shows the handle of a site that no longer exists', function (): void {
    expect(deadLinkSources(new Source('entry', 'geloescht', 'fr', 'link'))[0]['site'])->toBe('fr');
});

it('leaves the site out while the project has one site', function (): void {
    Site::setSites(['de' => ['name' => 'Deutsch', 'url' => '/', 'locale' => 'de_CH']]);

    expect(deadLinkSources(new Source('entry', 'geloescht', 'de', 'link'))[0]['site'])->toBeNull();
});
