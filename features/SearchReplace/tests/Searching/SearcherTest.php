<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Access\Authorizable;
use Vitamin2\Juicebox\Features\SearchReplace\Data\Item;
use Vitamin2\Juicebox\Features\SearchReplace\Data\Occurrence;
use Vitamin2\Juicebox\Features\SearchReplace\Data\SearchResult;
use Vitamin2\Juicebox\Features\SearchReplace\Searching\Searcher;
use Vitamin2\Juicebox\Features\SearchReplace\Searching\TextFields;
use Vitamin2\Juicebox\Features\SearchReplace\Tests\__Fixtures__\SearchReplaceFixtures;

covers(Searcher::class, TextFields::class, SearchResult::class, Item::class, Occurrence::class);

beforeEach(function (): void {
    SearchReplaceFixtures::sites();
    SearchReplaceFixtures::blueprints();
});

function searchReplaceFor(string $term, bool $matchCase = false, ?Authorizable $user = null): SearchResult
{
    return resolve(Searcher::class)->search($term, $user ?? SearchReplaceFixtures::superUser(), $matchCase);
}

/**
 * The ids of the occurrences, in the order the search finds them.
 *
 * @return array<int, string>
 */
function searchReplaceIds(): array
{
    return searchReplaceFor('team')->occurrences
        ->map(fn (Occurrence $occurrence): string => $occurrence->id())
        ->all();
}

/**
 * Every occurrence as "type id site path offset length", in the order the search finds them.
 *
 * @return array<int, string>
 */
function searchReplaceOccurrences(string $term, bool $matchCase = false, ?Authorizable $user = null): array
{
    return searchReplaceFor($term, $matchCase, $user)->occurrences
        ->map(fn (Occurrence $occurrence): string => implode(' ', [
            $occurrence->item->type,
            $occurrence->item->id,
            $occurrence->item->site,
            $occurrence->path,
            $occurrence->offset,
            $occurrence->length,
        ]))
        ->all();
}

it('finds the term in the text fields of entries, terms and global sets in every site', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', SearchReplaceFixtures::data('page'));
    SearchReplaceFixtures::entry('about', ['title' => 'Our team', 'lead' => 'A team of five.'], 'en', 'ueber-uns');
    SearchReplaceFixtures::term('news', [
        'de' => ['title' => 'News', 'description' => 'Neues vom Team.'],
        'en' => ['description' => 'Team news'],
    ]);
    SearchReplaceFixtures::globalSet('footer', [
        'de' => ['claim' => 'Ihr Team in Bern', 'social' => 'https://example.com/team'],
        'en' => ['claim' => 'Your team'],
    ]);

    expect(searchReplaceOccurrences('team'))->toEqualCanonicalizing([
        'entry ueber-uns de title 6 4',
        'entry ueber-uns de lead 4 4',
        'entry ueber-uns de body 17 4',
        'entry ueber-uns de content.0.content.1.text 0 4',
        'entry ueber-uns de content.1.attrs.values.text 0 4',
        'entry ueber-uns de content.2.content.0.content.0.content.0.text 0 4',
        'entry ueber-uns de subtitle.0.text 0 4',
        'entry ueber-uns de sections.0.text 4 4',
        'entry ueber-uns de sections.1.cards.0.label 0 4',
        'entry ueber-uns de facts.0.value 9 4',
        'entry ueber-uns de seo.description 0 4',
        'entry about en title 4 4',
        'entry about en lead 2 4',
        'term tags::news de description 10 4',
        'term tags::news en description 0 4',
        'global footer de claim 4 4',
        'global footer en claim 5 4',
    ]);
});

it('finds nothing in a site without content', function (): void {
    expect(searchReplaceFor('team'))
        ->occurrences->toBeEmpty()
        ->truncated->toBeFalse();
});

it('describes each item by its type, id, site and title', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team']);
    SearchReplaceFixtures::entry('team', ['lead' => 'Team']);
    SearchReplaceFixtures::term('news', ['de' => ['title' => 'News', 'description' => 'Team']]);
    SearchReplaceFixtures::globalSet('footer', ['en' => ['claim' => 'Team']]);

    expect(searchReplaceFor('team')->occurrences
        ->map(fn (Occurrence $occurrence): string => "{$occurrence->item->type} {$occurrence->item->id} {$occurrence->item->site} {$occurrence->item->title}")
        ->all())->toEqualCanonicalizing([
            'entry ueber-uns de Unser Team',
            'entry team de team',
            'term tags::news de News',
            'global footer en Footer',
        ]);
});

it('matches the case only when asked to', function (string $term, bool $matchCase, array $occurrences): void {
    SearchReplaceFixtures::entry('praxis', ['title' => 'Unsere Ärzte', 'lead' => 'Die ärzte im Team, das team. GROẞ']);

    expect(searchReplaceOccurrences($term, $matchCase))->toBe($occurrences);
})->with([
    'ignored' => ['team', false, ['entry praxis de lead 14 4', 'entry praxis de lead 24 4']],
    'matched' => ['team', true, ['entry praxis de lead 24 4']],
    'ignored for umlauts' => ['ÄRZTE', false, ['entry praxis de title 7 6', 'entry praxis de lead 4 6']],
    'matched for umlauts' => ['Ärzte', true, ['entry praxis de title 7 6']],
    'ignored where the cases differ in length' => ['groß', false, ['entry praxis de lead 30 6']],
]);

it('ignores the case by default', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team']);

    expect(resolve(Searcher::class)->search('team', SearchReplaceFixtures::superUser())->occurrences)->toHaveCount(1);
});

it('finds every occurrence in a string', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Team, Team und TEAM']);

    expect(searchReplaceOccurrences('team'))->toBe([
        'entry ueber-uns de title 0 4',
        'entry ueber-uns de title 6 4',
        'entry ueber-uns de title 15 4',
    ]);
});

it('treats the term as plain text', function (): void {
    SearchReplaceFixtures::entry('preise', ['title' => 'Preis (CHF/Std.): 120', 'lead' => 'Preis CHF Std: 120']);

    expect(searchReplaceOccurrences('(CHF/Std.)'))->toBe(['entry preise de title 6 10']);
});

it('finds nothing for an empty term', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team']);

    expect(searchReplaceFor(''))
        ->occurrences->toBeEmpty()
        ->truncated->toBeFalse();
});

it('does not find a match that crosses a Bard mark', function (string $term): void {
    SearchReplaceFixtures::entry('ueber-uns', SearchReplaceFixtures::data('page'));

    expect(searchReplaceOccurrences($term))->toBeEmpty();
})->with(['starkes Team', 'für Sie']);

it('leaves out :dataset', function (string $term): void {
    SearchReplaceFixtures::entry('ueber-uns', SearchReplaceFixtures::data('page'));

    expect(searchReplaceOccurrences($term))->toBeEmpty();
})->with([
    'slugs' => 'team-anker',
    'links, also in sets and Bard marks' => 'example.com',
    'relations' => 'team-eintrag',
    'select values' => 'team-farbe',
    'assets' => 'bilder/team',
    'Bard saved as HTML' => 'class=',
    'fields the blueprint does not have' => 'intern',
    'sets the field no longer has' => 'Team alt',
]);

it('lists an inherited value only on the origin', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team', 'lead' => 'Das Team']);
    SearchReplaceFixtures::entry('about', ['title' => 'Our team'], 'en', 'ueber-uns');

    expect(searchReplaceOccurrences('team'))->toEqualCanonicalizing([
        'entry ueber-uns de title 6 4',
        'entry ueber-uns de lead 4 4',
        'entry about en title 4 4',
    ]);
});

it('leaves out what the user may not edit', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Team']);
    SearchReplaceFixtures::entry('about', ['title' => 'Team'], 'en', 'ueber-uns');
    SearchReplaceFixtures::entry('news-1', ['title' => 'Team'], collection: 'news');
    SearchReplaceFixtures::term('news', ['de' => ['title' => 'Team']]);
    SearchReplaceFixtures::globalSet('footer', ['de' => ['claim' => 'Team'], 'en' => ['claim' => 'Team']]);
    $user = SearchReplaceFixtures::userWith('access de site', 'edit pages entries', 'edit footer globals');

    expect(searchReplaceOccurrences('team', user: $user))->toEqualCanonicalizing([
        'entry ueber-uns de title 0 4',
        'global footer de claim 0 4',
    ]);
});

it('skips an item whose blueprint is gone', function (): void {
    SearchReplaceFixtures::term('news', ['de' => ['blueprint' => 'gone', 'title' => 'Team']]);

    expect(searchReplaceOccurrences('team'))->toBeEmpty();
});

it('stops at 500 occurrences and tells whether there are more', function (int $count, bool $truncated): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => trim(str_repeat('ab ', $count))]);

    expect(searchReplaceFor('ab'))
        ->occurrences->toHaveCount(500)
        ->truncated->toBe($truncated);
})->with([
    'exactly 500' => [500, false],
    '501' => [501, true],
]);

it('keeps the ids for the same content and gives new ones once a value changes', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team', 'lead' => 'Das Team']);
    $first = searchReplaceIds();
    $again = searchReplaceIds();

    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team!', 'lead' => 'Das Team']);
    $changed = searchReplaceIds();

    expect($again)->toBe($first)
        ->and($changed[0])->not->toBe($first[0])
        ->and($changed[1])->toBe($first[1]);
});
