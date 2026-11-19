<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaved;
use Statamic\Events\GlobalVariablesSaved;
use Statamic\Events\LocalizedTermSaved;
use Statamic\Globals\GlobalSet;
use Statamic\Taxonomies\Term;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Batch;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Patch;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Replacer;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Result;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\SkippedItem;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\SkipReason;
use Vitamin2\Juicebox\Features\SearchReplace\Tests\__Fixtures__\SearchReplaceFixtures;

covers(Replacer::class, Result::class, SkippedItem::class, SkipReason::class);

beforeEach(function (): void {
    SearchReplaceFixtures::sites();
    SearchReplaceFixtures::blueprints();
    $this->travelTo(CarbonImmutable::parse('2026-11-19 10:01:00'));
});

function applySearchReplace(Batch $batch): Result
{
    return resolve(Replacer::class)->apply($batch);
}

it('replaces the matches in every text field of an entry, in Bard text and in sets too', function (): void {
    $page = SearchReplaceFixtures::data('page');
    SearchReplaceFixtures::entry('ueber-uns', $page);
    $batch = SearchReplaceFixtures::batch();

    $result = applySearchReplace($batch);

    $expected = $page;
    Arr::set($expected, 'title', 'Unser Praxis');
    Arr::set($expected, 'lead', 'Das Praxis stellt sich vor.');
    Arr::set($expected, 'body', 'Mehr über das **Praxis**.');
    Arr::set($expected, 'content.0.content.1.text', 'Praxis');
    Arr::set($expected, 'content.1.attrs.values.text', 'Praxis first');
    Arr::set($expected, 'content.2.content.0.content.0.content.0.text', 'Praxis Bern');
    Arr::set($expected, 'subtitle.0.text', 'Praxis-Untertitel');
    Arr::set($expected, 'sections.0.text', 'Das Praxis');
    Arr::set($expected, 'sections.1.cards.0.label', 'Praxis A');
    Arr::set($expected, 'facts.0.value', '1999 mit Praxis');
    Arr::set($expected, 'seo.description', 'Praxis und Werte');

    expect(SearchReplaceFixtures::values('entry', 'ueber-uns'))->toBe($expected)
        ->and(Arr::except(SearchReplaceFixtures::savedEntry('ueber-uns'), ['id', 'blueprint']))->toEqual($expected)
        ->and($result)->toEqual(new Result(CarbonImmutable::parse('2026-11-19 10:01:00'), 11, 1, []));
});

it('replaces the matches of a localization in its own values only', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team', 'lead' => 'Das Team']);
    SearchReplaceFixtures::entry('about', ['title' => 'Our team'], 'en', 'ueber-uns');

    $result = applySearchReplace(SearchReplaceFixtures::batch());

    expect(SearchReplaceFixtures::values('entry', 'ueber-uns'))->toBe(['title' => 'Unser Praxis', 'lead' => 'Das Praxis'])
        ->and(SearchReplaceFixtures::values('entry', 'about', 'en'))->toBe(['title' => 'Our Praxis'])
        ->and($result)->toEqual(new Result(CarbonImmutable::parse('2026-11-19 10:01:00'), 3, 2, []));
});

it('replaces the matches of a term in each site', function (): void {
    SearchReplaceFixtures::term('news', [
        'de' => ['title' => 'News', 'description' => 'Neues vom Team.'],
        'en' => ['description' => 'Team news, team views'],
    ]);

    $result = applySearchReplace(SearchReplaceFixtures::batch());

    expect(SearchReplaceFixtures::values('term', 'tags::news'))->toBe(['title' => 'News', 'description' => 'Neues vom Praxis.'])
        ->and(SearchReplaceFixtures::values('term', 'tags::news', 'en'))->toBe(['description' => 'Praxis news, Praxis views'])
        ->and($result)->toEqual(new Result(CarbonImmutable::parse('2026-11-19 10:01:00'), 3, 2, []));
});

it('replaces the matches of a global set in each site', function (): void {
    SearchReplaceFixtures::globalSet('footer', [
        'de' => ['claim' => 'Ihr Team in Bern', 'social' => 'https://example.com/team'],
        'en' => ['claim' => 'Your team'],
    ]);

    $result = applySearchReplace(SearchReplaceFixtures::batch(user: SearchReplaceFixtures::editor()));

    expect(SearchReplaceFixtures::values('global', 'footer'))->toBe(['claim' => 'Ihr Praxis in Bern', 'social' => 'https://example.com/team'])
        ->and(SearchReplaceFixtures::values('global', 'footer', 'en'))->toBe(['claim' => 'Your Praxis'])
        ->and($result)->toEqual(new Result(CarbonImmutable::parse('2026-11-19 10:01:00'), 2, 2, []));
});

it('keeps an entry and a global set apart that share their id', function (): void {
    SearchReplaceFixtures::entry('footer', ['title' => 'Team']);
    SearchReplaceFixtures::globalSet('footer', ['de' => ['claim' => 'Ihr Team']]);

    $result = applySearchReplace(SearchReplaceFixtures::batch());

    expect(SearchReplaceFixtures::values('entry', 'footer'))->toBe(['title' => 'Praxis'])
        ->and(SearchReplaceFixtures::values('global', 'footer'))->toBe(['claim' => 'Ihr Praxis'])
        ->and($result)->toEqual(new Result(CarbonImmutable::parse('2026-11-19 10:01:00'), 2, 2, []));
});

it('saves each item once, with every replacement in it', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', SearchReplaceFixtures::data('page'));
    SearchReplaceFixtures::entry('about', ['title' => 'Our team', 'lead' => 'A team of five.'], 'en');
    SearchReplaceFixtures::term('news', ['de' => ['description' => 'Team'], 'en' => ['description' => 'Team']]);
    SearchReplaceFixtures::globalSet('footer', ['de' => ['claim' => 'Team'], 'en' => ['claim' => 'Team']]);
    $batch = SearchReplaceFixtures::batch();
    Event::fake([EntrySaved::class, LocalizedTermSaved::class, GlobalVariablesSaved::class]);

    applySearchReplace($batch);

    Event::assertDispatchedTimes(EntrySaved::class, 2);
    Event::assertDispatched(fn (EntrySaved $event): bool => $event->entry->get('title') === 'Unser Praxis'
        && data_get($event->entry->get('content'), '1.attrs.values.text') === 'Praxis first');
    Event::assertDispatched(fn (EntrySaved $event): bool => $event->entry->get('lead') === 'A Praxis of five.');
    Event::assertDispatchedTimes(LocalizedTermSaved::class, 2);
    Event::assertDispatchedTimes(GlobalVariablesSaved::class, 2);
});

it('records the user of the batch as the last one to change an entry or term', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team', 'updated_by' => 'someone', 'updated_at' => 1700000000]);
    SearchReplaceFixtures::term('news', ['de' => ['description' => 'Team']]);

    applySearchReplace(SearchReplaceFixtures::batch(user: SearchReplaceFixtures::editor()));

    $now = CarbonImmutable::parse('2026-11-19 10:01:00')->getTimestamp();

    expect(SearchReplaceFixtures::values('entry', 'ueber-uns'))->toBe(['title' => 'Unser Praxis', 'updated_by' => 'editor-1', 'updated_at' => $now])
        ->and(SearchReplaceFixtures::values('term', 'tags::news'))->toBe(['description' => 'Praxis', 'updated_by' => 'editor-1', 'updated_at' => $now]);
});

it('leaves the last change of an entry as it is when the batch :dataset', function (?string $user): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team', 'updated_by' => 'someone', 'updated_at' => 1700000000]);

    applySearchReplace(SearchReplaceFixtures::batch(user: $user));

    expect(SearchReplaceFixtures::values('entry', 'ueber-uns'))->toBe(['title' => 'Unser Praxis', 'updated_by' => 'someone', 'updated_at' => 1700000000]);
})->with([
    'names a user who is gone' => 'editor-gone',
    'names no user' => null,
]);

it('skips a missing :dataset and writes the other items', function (SkippedItem $skipped, Closure $delete): void {
    SearchReplaceFixtures::entry('team', ['title' => 'Team', 'lead' => 'Das Team']);
    $entry = SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team']);
    $term = SearchReplaceFixtures::term('news', ['de' => ['description' => 'Team']]);
    $set = SearchReplaceFixtures::globalSet('footer', ['de' => ['claim' => 'Team']]);
    $batch = SearchReplaceFixtures::batch();
    $delete($entry, $term, $set);

    $result = applySearchReplace($batch);

    expect(SearchReplaceFixtures::values('entry', 'team'))->toBe(['title' => 'Praxis', 'lead' => 'Das Praxis'])
        ->and($result->replaced)->toBe(4)
        ->and($result->items)->toBe(3)
        ->and($result->skipped)->toEqual([$skipped]);
})->with([
    'entry' => [
        fn (): SkippedItem => new SkippedItem('entry', 'ueber-uns', 'de', SkipReason::Missing),
        fn (Entry $entry): mixed => $entry->delete(),
    ],
    'term' => [
        fn (): SkippedItem => new SkippedItem('term', 'tags::news', 'de', SkipReason::Missing),
        fn (Entry $entry, Term $term): mixed => $term->delete(),
    ],
    'global set' => [
        fn (): SkippedItem => new SkippedItem('global', 'footer', 'de', SkipReason::Missing),
        fn (Entry $entry, Term $term, GlobalSet $set): mixed => $set->delete(),
    ],
]);

it('skips an entry with a working copy', function (): void {
    $entry = SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team']);
    SearchReplaceFixtures::term('news', ['de' => ['description' => 'Team']]);
    $batch = SearchReplaceFixtures::batch();
    SearchReplaceFixtures::workingCopy($entry);

    $result = applySearchReplace($batch);

    expect(SearchReplaceFixtures::values('entry', 'ueber-uns'))->toBe(['title' => 'Unser Team'])
        ->and(SearchReplaceFixtures::values('term', 'tags::news'))->toBe(['description' => 'Praxis'])
        ->and($result)->toEqual(new Result(CarbonImmutable::parse('2026-11-19 10:01:00'), 1, 1, [
            new SkippedItem('entry', 'ueber-uns', 'de', SkipReason::WorkingCopy),
        ]));
});

it('skips an item as a whole when one of its fields :dataset', function (mixed $lead): void {
    $entry = SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team', 'lead' => 'Das Team', 'notes' => 'Team intern']);
    SearchReplaceFixtures::entry('team', ['title' => 'Team']);
    $batch = SearchReplaceFixtures::batch();
    $entry->set('lead', $lead);
    $entry->save();
    Event::fake([EntrySaved::class]);

    $result = applySearchReplace($batch);

    expect(SearchReplaceFixtures::values('entry', 'ueber-uns'))->toBe(['title' => 'Unser Team', 'lead' => $lead, 'notes' => 'Team intern'])
        ->and(SearchReplaceFixtures::values('entry', 'team'))->toBe(['title' => 'Praxis'])
        ->and($result)->toEqual(new Result(CarbonImmutable::parse('2026-11-19 10:01:00'), 1, 1, [
            new SkippedItem('entry', 'ueber-uns', 'de', SkipReason::Changed),
        ]));

    Event::assertDispatchedTimes(EntrySaved::class, 1);
})->with([
    'changed since the search' => 'Das neue Team',
    'lost its text' => null,
]);

it('reports the skipped items in the order of the batch', function (): void {
    $entry = SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser neues Team']);
    $batch = new Batch('9d3c1f2a-0b4e-4c6d-8e7f-000000000001', null, 'Team', 'Praxis', false, CarbonImmutable::now(), [
        new Patch('entry', 'team', 'de', 'title', 'Team', 'Praxis', 1),
        new Patch('entry', 'ueber-uns', 'de', 'title', 'Unser Team', 'Unser Praxis', 1),
        new Patch('global', 'footer', 'de', 'claim', 'Team', 'Praxis', 1),
    ]);

    expect(applySearchReplace($batch)->skipped)->toEqual([
        new SkippedItem('entry', 'team', 'de', SkipReason::Missing),
        new SkippedItem('entry', 'ueber-uns', 'de', SkipReason::Changed),
        new SkippedItem('global', 'footer', 'de', SkipReason::Missing),
    ])->and($entry->get('title'))->toBe('Unser neues Team');
});
