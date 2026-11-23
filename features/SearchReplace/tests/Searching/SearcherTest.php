<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Access\Authorizable;
use Vitamin2\Juicebox\Features\SearchReplace\Data\Item;
use Vitamin2\Juicebox\Features\SearchReplace\Data\Occurrence;
use Vitamin2\Juicebox\Features\SearchReplace\Data\SearchResult;
use Vitamin2\Juicebox\Features\SearchReplace\Data\SplitOccurrence;
use Vitamin2\Juicebox\Features\SearchReplace\Searching\Searcher;
use Vitamin2\Juicebox\Features\SearchReplace\Searching\TextFields;
use Vitamin2\Juicebox\Features\SearchReplace\Tests\__Fixtures__\SearchReplaceFixtures;

covers(Searcher::class, TextFields::class, SearchResult::class, Item::class, Occurrence::class, SplitOccurrence::class);

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

/**
 * Every match across Bard formatting as "type id site label offset length | before[text]after", in the order the search
 * finds them.
 *
 * @return array<int, string>
 */
function searchReplaceSplit(string $term, bool $matchCase = false): array
{
    return searchReplaceFor($term, $matchCase)->split
        ->map(fn (SplitOccurrence $match): string => implode(' ', [
            $match->item->type,
            $match->item->id,
            $match->item->site,
            $match->label,
            $match->offset,
            $match->length,
        ])." | {$match->contextBefore()}[{$match->text()}]{$match->contextAfter()}")
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
        'entry ueber-uns de sections.3.rows.0.cells.0 0 4',
        'entry ueber-uns de facts.0.value 9 4',
        'entry ueber-uns de facts.0.sources.0 0 4',
        'entry ueber-uns de seo.description 0 4',
        'entry ueber-uns de seo.keywords.0 0 4',
        'entry ueber-uns de highlights.0 0 4',
        'entry ueber-uns de prices.1.cells.0 0 4',
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

it('lists a match that crosses a Bard mark as split, to be edited by hand, and replaces none of it', function (string $term, string $split): void {
    SearchReplaceFixtures::entry('ueber-uns', SearchReplaceFixtures::data('page'));

    expect(searchReplaceOccurrences($term))->toBeEmpty()
        ->and(searchReplaceSplit($term))->toBe([$split]);
})->with([
    'into a bold word' => ['starkes Team', 'entry ueber-uns de Inhalt 4 12 | Ein [starkes Team] für Sie'],
    'out of a bold word' => ['Team für', 'entry ueber-uns de Inhalt 12 9 | Ein starkes [Team für] Sie'],
    'into a link' => ['für Sie', 'entry ueber-uns de Inhalt 17 8 | Ein starkes Team [für Sie]'],
    'across three nodes' => ['starkes Team für', 'entry ueber-uns de Inhalt 4 17 | Ein [starkes Team für] Sie'],
]);

it('finds a match inside one Bard text node in that node, with the text of the nodes around it as its context', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', SearchReplaceFixtures::data('page'));

    $occurrence = searchReplaceFor('team')->occurrences
        ->firstOrFail(fn (Occurrence $occurrence): bool => $occurrence->path === 'content.0.content.1.text');

    expect([$occurrence->value, $occurrence->offset, $occurrence->length])->toBe(['Team', 0, 4])
        ->and($occurrence->contextBefore())->toBe('Ein starkes ')
        ->and($occurrence->contextAfter())->toBe(' für Sie');
});

it('finds a match that starts or ends where a Bard text node does in that node', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['content' => [['type' => 'paragraph', 'content' => [
        ['type' => 'text', 'text' => 'Das Team'],
        ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'Team'],
        ['type' => 'text', 'text' => 'Team ist da'],
    ]]]]);

    expect(searchReplaceOccurrences('team'))->toBe([
        'entry ueber-uns de content.0.content.0.text 4 4',
        'entry ueber-uns de content.0.content.1.text 0 4',
        'entry ueber-uns de content.0.content.2.text 0 4',
    ])->and(searchReplaceSplit('team'))->toBeEmpty();
});

it('lists the matches that can be replaced and the split ones apart, each in the order the search finds them', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', [
        'title' => 'Acme Corp',
        'content' => [['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'Acme'],
            ['type' => 'text', 'text' => ' Corp und Acme Corp'],
        ]]],
        'subtitle' => [['type' => 'text', 'text' => 'Acme Corp']],
    ]);

    expect(searchReplaceOccurrences('acme corp'))->toBe([
        'entry ueber-uns de title 0 9',
        'entry ueber-uns de content.0.content.1.text 10 9',
        'entry ueber-uns de subtitle.0.text 0 9',
    ])->and(searchReplaceSplit('acme corp'))->toBe([
        'entry ueber-uns de Inhalt 0 9 | [Acme Corp] und Acme Corp',
    ])->and(searchReplaceFor('acme corp')->matches->map(fn (Occurrence|SplitOccurrence $match): string => $match::class)->all())->toBe([
        Occurrence::class,
        SplitOccurrence::class,
        Occurrence::class,
        Occurrence::class,
    ]);
});

it('counts the split matches toward the 500', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', [
        'title' => trim(str_repeat('ab ', 499)),
        'content' => [['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'a'],
            ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'b a'],
            ['type' => 'text', 'text' => 'b'],
        ]]],
    ]);

    expect(searchReplaceFor('ab'))
        ->occurrences->toHaveCount(499)
        ->split->toHaveCount(1)
        ->truncated->toBeTrue();
});

it('finds the term in the items of a list and the cells of a table, also in sets, grids and groups', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', [
        'highlights' => ['Team-Event', 2024, 'Ein Team'],
        'prices' => [['cells' => ['Team', 'CHF 90']], ['cells' => ['Coaching', 'pro Team']]],
        'sections' => [['id' => 'm2b3c4d5', 'type' => 'pricing', 'enabled' => true, 'rows' => [['cells' => ['', 'Team']]]]],
        'facts' => [['id' => 'f1a2b3c4', 'sources' => ['Team-Chronik']]],
        'seo' => ['keywords' => ['Werte', 'Team']],
    ]);

    expect(searchReplaceOccurrences('team'))->toBe([
        'entry ueber-uns de sections.0.rows.0.cells.1 0 4',
        'entry ueber-uns de facts.0.sources.0 0 4',
        'entry ueber-uns de seo.keywords.1 0 4',
        'entry ueber-uns de highlights.0 0 4',
        'entry ueber-uns de highlights.2 4 4',
        'entry ueber-uns de prices.0.cells.0 0 4',
        'entry ueber-uns de prices.1.cells.1 4 4',
    ]);
});

it('finds the term in the fields imported from a fieldset, with and without a prefix', function (): void {
    SearchReplaceFixtures::entry('artikel', SearchReplaceFixtures::data('article'), collection: 'articles');

    expect(searchReplaceOccurrences('team'))->toBe([
        'entry artikel de heading 0 4',
        'entry artikel de text 9 4',
        'entry artikel de hero_heading 5 4',
        'entry artikel de hero_text 14 4',
        'entry artikel de blocks.0.heading 6 4',
        'entry artikel de blocks.0.cta_text 8 4',
        'entry artikel de body.0.attrs.values.text 8 4',
        'entry artikel de body.0.attrs.values.box_heading 4 4',
        'entry artikel de rows.0.heading 6 4',
        'entry artikel de rows.0.side_text 10 4',
        'entry artikel de meta.text 9 4',
        'entry artikel de meta.og_heading 3 4',
    ]);
});

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
        ->split->toBeEmpty()
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
