<?php

declare(strict_types=1);

use Vitamin2\Juicebox\Features\DeadLinks\Data\Link;
use Vitamin2\Juicebox\Features\DeadLinks\Data\Source;
use Vitamin2\Juicebox\Features\DeadLinks\Scanning\ContentScanner;
use Vitamin2\Juicebox\Features\DeadLinks\Tests\__Fixtures__\DeadLinksFixtures;

covers(ContentScanner::class, Link::class, Source::class);

beforeEach(function (): void {
    DeadLinksFixtures::sites();
});

/**
 * Every scanned URL with the places it is in, sorted.
 *
 * @return array<string, array<int, string>>
 */
function scannedDeadLinks(): array
{
    return resolve(ContentScanner::class)->scan()
        ->mapWithKeys(fn (Link $link): array => [$link->url => collect($link->sources)
            ->map(fn (Source $source): string => "{$source->type} {$source->id} {$source->site} {$source->field}")
            ->sort()
            ->values()
            ->all()])
        ->sortKeys()
        ->all();
}

it('finds the links of entries, terms, global sets and navigations in every site', function (): void {
    DeadLinksFixtures::entry('ueber-uns', DeadLinksFixtures::data('page'));
    DeadLinksFixtures::entry('about', ['title' => 'About', 'link' => 'https://example.com/en'], 'en', 'ueber-uns');
    DeadLinksFixtures::term('news', [
        'de' => ['description' => 'Mehr auf https://example.com/team.'],
        'en' => ['link' => '/en/news'],
    ]);
    DeadLinksFixtures::globalSet('footer', [
        'de' => ['imprint' => 'entry::impressum'],
        'en' => ['imprint' => '/en/imprint'],
    ]);
    DeadLinksFixtures::nav('main', [
        'de' => [['id' => 'n1', 'entry' => 'ueber-uns', 'children' => [['id' => 'n2', 'title' => 'Team', 'url' => 'https://example.com/team']]]],
        'en' => [['id' => 'n3', 'title' => 'Home', 'url' => '/en/']],
    ]);

    expect(scannedDeadLinks())->toBe([
        '/' => ['entry ueber-uns de home'],
        '/angebot' => ['entry ueber-uns de body'],
        '/en/' => ['nav main en url'],
        '/en/imprint' => ['global footer en imprint'],
        '/en/news' => ['term tags::news en link'],
        '/standorte' => ['entry ueber-uns de sections'],
        'asset::assets::bilder/team.jpg' => ['entry ueber-uns de content'],
        'asset::assets::docs/preise.pdf' => ['entry ueber-uns de download'],
        'entry::angebot' => ['entry ueber-uns de content'],
        'entry::impressum' => ['global footer de imprint'],
        'entry::kontakt' => ['entry ueber-uns de link'],
        'entry::team' => ['entry ueber-uns de sections'],
        'entry::ueber-uns' => ['nav main de entry'],
        'https://example.com/bard' => ['entry ueber-uns de content'],
        'https://example.com/en' => ['entry about en link'],
        'https://example.com/team' => [
            'entry ueber-uns de sections',
            'entry ueber-uns de website',
            'nav main de url',
            'term tags::news de description',
        ],
        'https://example.org/news' => ['entry ueber-uns de body'],
        'www.example.net' => ['entry ueber-uns de content'],
    ]);
});

it('finds nothing in a site without content', function (): void {
    expect(scannedDeadLinks())->toBeEmpty();
});

it('lists the links of a localization from its own values, not from its origin', function (): void {
    DeadLinksFixtures::entry('ueber-uns', ['link' => '/kontakt']);
    DeadLinksFixtures::entry('about', ['title' => 'About'], 'en', 'ueber-uns');

    expect(scannedDeadLinks())->toBe(['/kontakt' => ['entry ueber-uns de link']]);
});

it('lists every place of a link once', function (): void {
    DeadLinksFixtures::nav('main', [
        'de' => [
            ['id' => 'n1', 'title' => 'Shop', 'url' => 'https://example.com/shop'],
            ['id' => 'n2', 'title' => 'Shop again', 'url' => 'https://example.com/shop'],
        ],
    ]);

    expect(scannedDeadLinks())->toBe(['https://example.com/shop' => ['nav main de url']]);
});

it('keeps a link whose target is a number', function (): void {
    DeadLinksFixtures::entry('archiv', ['text' => '<a href="2024">2024</a>', 'teaser' => '[2024](2024)']);

    expect(scannedDeadLinks())->toBe(['2024' => ['entry archiv de teaser', 'entry archiv de text']]);
});

it('takes navigation items to entries and URLs at every level, but not placeholders', function (): void {
    DeadLinksFixtures::nav('footer', [
        'de' => [
            ['id' => 'n1', 'title' => 'Menu', 'url' => '#', 'children' => [
                ['id' => 'n2', 'entry' => 'kontakt', 'children' => [['id' => 'n3', 'title' => 'Mail', 'url' => 'mailto:info@example.com']]],
                ['id' => 'n4', 'title' => 'Text only'],
            ]],
        ],
    ]);

    expect(scannedDeadLinks())->toBe(['entry::kontakt' => ['nav footer de entry']]);
});

it('drops the links that start with an excluded prefix', function (): void {
    config(['juicebox.dead_links.exclude' => ['https://example.com/', '/intern']]);
    DeadLinksFixtures::entry('ueber-uns', [
        'shop' => 'https://example.com/shop',
        'other' => 'https://example.org/https://example.com/',
        'intern' => '/intern/team',
        'kontakt' => '/kontakt',
    ]);

    expect(scannedDeadLinks())->toBe([
        '/kontakt' => ['entry ueber-uns de kontakt'],
        'https://example.org/https://example.com/' => ['entry ueber-uns de other'],
    ]);
});

it('ignores excluded prefixes that are not strings', function (): void {
    config(['juicebox.dead_links.exclude' => [['/kontakt'], '/intern']]);
    DeadLinksFixtures::entry('ueber-uns', ['kontakt' => '/kontakt', 'intern' => '/intern']);

    expect(scannedDeadLinks())->toBe(['/kontakt' => ['entry ueber-uns de kontakt']]);
});
