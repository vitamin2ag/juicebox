<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Statamic\Auth\File\User;
use Statamic\Facades\Site;
use Vitamin2\Juicebox\Features\SearchReplace\Data\Occurrence;
use Vitamin2\Juicebox\Features\SearchReplace\Http\SearchReplacePage;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Batch;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\BatchRepository;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Result;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\SkippedItem;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\SkipReason;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Undo;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\UndoResult;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\WrittenItem;
use Vitamin2\Juicebox\Features\SearchReplace\Searching\Searcher;
use Vitamin2\Juicebox\Features\SearchReplace\Searching\TextFields;
use Vitamin2\Juicebox\Features\SearchReplace\ServiceProvider;
use Vitamin2\Juicebox\Features\SearchReplace\Tests\__Fixtures__\SearchReplaceFixtures;

covers(SearchReplacePage::class, ServiceProvider::class);

/*
 * The switch is read while the app boots, so these tests turn it on in beforeAll(), as a project does.
 */
beforeAll(function (): void {
    putenv('JUICEBOX_SEARCH_REPLACE_ENABLED=true');
});

afterAll(function (): void {
    putenv('JUICEBOX_SEARCH_REPLACE_ENABLED');
});

beforeEach(function (): void {
    Storage::fake('local');
    config(['statamic.editions.pro' => true]);
    SearchReplaceFixtures::sites();
    SearchReplaceFixtures::blueprints();
});

/**
 * The ids of the occurrences of the term, in the order the search finds them.
 *
 * @return array<int, string>
 */
function searchReplacePageIds(string $term): array
{
    return resolve(Searcher::class)->search($term, SearchReplaceFixtures::superUser())->occurrences
        ->map(fn (Occurrence $occurrence): string => $occurrence->id())
        ->all();
}

/**
 * The props of the page for the query, as the signed-in user sees them once Inertia has called the closures.
 *
 * @return array<string, mixed>
 */
function searchReplacePageProps(string $query = ''): array
{
    return collect(resolve(SearchReplacePage::class)->toArray(Request::create("/cp/utilities/search-replace?{$query}")))
        ->map(fn (mixed $prop): mixed => $prop instanceof Closure ? $prop() : $prop)
        ->all();
}

/**
 * Saves a batch that the command applied with this result.
 *
 * @param  array<int, SkippedItem>  $skipped
 */
function searchReplaceAppliedBatch(array $skipped, ?string $user = null): void
{
    resolve(BatchRepository::class)->save(new Batch(
        '9d3c1f2a-0b4e-4c6d-8e7f-000000000001',
        $user,
        'Team',
        'Praxis',
        false,
        CarbonImmutable::parse('2026-11-19 10:00:00'),
        [],
        new Result(CarbonImmutable::parse('2026-11-19 10:01:00'), 5, 2, $skipped),
    ));
}

/**
 * The skipped items of the only batch, as the page lists them.
 */
function searchReplacePageSkipped(SkippedItem ...$skipped): mixed
{
    searchReplaceAppliedBatch(array_values($skipped));

    return data_get(searchReplacePageProps(), 'batches.0.skipped');
}

/**
 * A batch of the user marko that the command applied and that wrote an entry, with this undo.
 */
function searchReplaceWrittenBatch(?Undo $undo = null): Batch
{
    return new Batch(
        '9d3c1f2a-0b4e-4c6d-8e7f-000000000001',
        'marko',
        'Team',
        'Praxis',
        false,
        CarbonImmutable::parse('2026-11-19 10:00:00'),
        [],
        new Result(CarbonImmutable::parse('2026-11-19 10:01:00'), 1, 1, [], [
            new WrittenItem('entry', 'ueber-uns', 'de', [['handle' => 'title', 'before' => 'Unser Team', 'after' => 'Unser Praxis']]),
        ]),
        $undo,
    );
}

/**
 * Saves a user with this id who may use the utility, or a super user.
 */
function searchReplacePageUser(string $id, bool $super = false): User
{
    $user = SearchReplaceFixtures::userWith('access search_replace utility');
    $user->id($id);

    if ($super) {
        $user->makeSuper();
    }

    $user->save();

    return $user;
}

/**
 * Saves a user with this name, which a batch names by its id.
 */
function searchReplaceUser(?string $name): void
{
    $user = new User;
    $user->id('marko');
    $user->email('marko@example.com');

    if ($name !== null) {
        $user->set('name', $name);
    }

    $user->save();
}

it('opens the utility without a search', function (): void {
    $this->actingAs(SearchReplaceFixtures::superUser())
        ->get(SearchReplaceFixtures::cpUrl('utilities.search-replace'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('search-replace::Index')
            ->where('search', '')
            ->where('matchCase', false)
            ->where('searchUrl', SearchReplaceFixtures::cpUrl('utilities.search-replace'))
            ->where('replaceUrl', SearchReplaceFixtures::cpUrl('utilities.search-replace.replace'))
            ->where('undoUrl', SearchReplaceFixtures::cpUrl('utilities.search-replace.undo'))
            ->where('results', null)
            ->where('batches', []));
});

it('lists the matches of the search by item, with the context of each one', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team', 'lead' => 'Das Team im Team']);
    SearchReplaceFixtures::term('news', ['de' => ['title' => 'News', 'description' => 'Neues vom Team.']]);
    SearchReplaceFixtures::globalSet('footer', ['de' => ['claim' => 'Team']]);
    $ids = searchReplacePageIds('team');

    $this->actingAs(SearchReplaceFixtures::superUser())
        ->get(SearchReplaceFixtures::cpUrl('utilities.search-replace', ['search' => 'team']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('search-replace::Index')
            ->where('search', 'team')
            ->where('matchCase', false)
            ->where('results', [
                'truncated' => false,
                'items' => [
                    [
                        'type' => 'entry',
                        'title' => 'Unser Team',
                        'editUrl' => SearchReplaceFixtures::cpUrl('collections.entries.edit', ['pages', 'ueber-uns']),
                        'site' => 'Deutsch',
                        'occurrences' => [
                            ['id' => $ids[0], 'field' => 'Titel', 'before' => 'Unser ', 'match' => 'Team', 'after' => ''],
                            ['id' => $ids[1], 'field' => 'Lead', 'before' => 'Das ', 'match' => 'Team', 'after' => ' im Team'],
                            ['id' => $ids[2], 'field' => 'Lead', 'before' => 'Das Team im ', 'match' => 'Team', 'after' => ''],
                        ],
                    ],
                    [
                        'type' => 'term',
                        'title' => 'News',
                        'editUrl' => SearchReplaceFixtures::cpUrl('taxonomies.terms.edit', ['tags', 'news', 'de']),
                        'site' => 'Deutsch',
                        'occurrences' => [
                            ['id' => $ids[3], 'field' => 'Beschreibung', 'before' => 'Neues vom ', 'match' => 'Team', 'after' => '.'],
                        ],
                    ],
                    [
                        'type' => 'global',
                        'title' => 'Footer',
                        'editUrl' => SearchReplaceFixtures::cpUrl('globals.variables.edit', ['footer', 'site' => 'de']),
                        'site' => 'Deutsch',
                        'occurrences' => [
                            ['id' => $ids[4], 'field' => 'Claim', 'before' => '', 'match' => 'Team', 'after' => ''],
                        ],
                    ],
                ],
            ]));
});

it('keeps the items apart that share their type, id or site', function (): void {
    SearchReplaceFixtures::entry('footer', ['title' => 'Team']);
    SearchReplaceFixtures::entry('kontakt', ['title' => 'Team']);
    SearchReplaceFixtures::globalSet('footer', ['de' => ['claim' => 'Team'], 'en' => ['claim' => 'Team']]);
    $this->actingAs(SearchReplaceFixtures::superUser());

    expect(data_get(searchReplacePageProps('search=team'), 'results.items.*.editUrl'))->toEqualCanonicalizing([
        SearchReplaceFixtures::cpUrl('collections.entries.edit', ['pages', 'footer']),
        SearchReplaceFixtures::cpUrl('collections.entries.edit', ['pages', 'kontakt']),
        SearchReplaceFixtures::cpUrl('globals.variables.edit', ['footer', 'site' => 'de']),
        SearchReplaceFixtures::cpUrl('globals.variables.edit', ['footer', 'site' => 'en']),
    ]);
});

it('searches with match case', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team', 'lead' => 'Das team']);

    $this->actingAs(SearchReplaceFixtures::superUser())
        ->get(SearchReplaceFixtures::cpUrl('utilities.search-replace', ['search' => 'team', 'match_case' => 1]))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('matchCase', true)
            ->has('results.items', 1)
            ->where('results.items.0.occurrences.0.field', 'Lead')
            ->has('results.items.0.occurrences', 1));
});

it('keeps the spaces a search starts or ends with, which the control panel trims from the query', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Das  Team']);

    $this->actingAs(SearchReplaceFixtures::superUser())
        ->get(SearchReplaceFixtures::cpUrl('utilities.search-replace').'?search=%20%20')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('search', '  ')
            ->where('results.items.0.occurrences.0.before', 'Das')
            ->where('results.items.0.occurrences.0.match', '  ')
            ->where('results.items.0.occurrences.0.after', 'Team'));
});

it('searches only when the page asks for the results, not when it reloads the batches', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Team']);
    resolve(BatchRepository::class)->save(SearchReplaceFixtures::batch());
    $searcher = Mockery::mock(Searcher::class, [resolve(TextFields::class)])->makePartial();
    $searcher->shouldReceive('search')->once()->passthru();
    $this->instance(Searcher::class, $searcher);

    $this->actingAs(SearchReplaceFixtures::superUser())
        ->get(SearchReplaceFixtures::cpUrl('utilities.search-replace', ['search' => 'team']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('results.items', 1)
            ->reloadOnly('batches', fn (AssertableInertia $reload): AssertableInertia => $reload
                ->missing('results')
                ->has('batches', 1)));
});

it('treats :dataset as no search', function (string $query): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Team']);

    $this->actingAs(SearchReplaceFixtures::superUser())
        ->get(SearchReplaceFixtures::cpUrl('utilities.search-replace').$query)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('search', '')
            ->where('results', null));
})->with([
    'an empty search' => '?search=',
    'a search that is not valid UTF-8' => '?search=%FF',
    'a list as search' => '?search[]=Team',
]);

it('lists no items when nothing matches', function (): void {
    $this->actingAs(SearchReplaceFixtures::superUser());

    expect(searchReplacePageProps('search=Team')['results'])->toBe(['truncated' => false, 'items' => []]);
});

it('tells the page that the search found more matches than it lists', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => trim(str_repeat('ab ', 501))]);
    $this->actingAs(SearchReplaceFixtures::superUser());

    $props = searchReplacePageProps('search=ab');

    expect(data_get($props, 'results.truncated'))->toBeTrue()
        ->and(data_get($props, 'results.items.0.occurrences'))->toHaveCount(500);
});

it('lists only the items the user may edit, for a user with the access search_replace utility permission', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Team']);
    SearchReplaceFixtures::entry('news-1', ['title' => 'Team'], collection: 'news');

    $this->actingAs(SearchReplaceFixtures::userWith('access search_replace utility', 'access de site', 'edit pages entries'))
        ->get(SearchReplaceFixtures::cpUrl('utilities.search-replace', ['search' => 'team']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('search-replace::Index')
            ->has('results.items', 1)
            ->where('results.items.0.editUrl', SearchReplaceFixtures::cpUrl('collections.entries.edit', ['pages', 'ueber-uns'])));
});

it('forbids the utility without the access search_replace utility permission', function (): void {
    $this->actingAs(SearchReplaceFixtures::userWith('access de site', 'edit pages entries'))
        ->getJson(SearchReplaceFixtures::cpUrl('utilities.search-replace', ['search' => 'team']))
        ->assertForbidden();
});

it('lists the latest ten batches, the newest first', function (): void {
    $batches = resolve(BatchRepository::class);
    collect(range(1, 11))->each(fn (int $number) => $batches->save(new Batch(
        sprintf('9d3c1f2a-0b4e-4c6d-8e7f-%012d', $number),
        null,
        'Team',
        'Praxis',
        false,
        CarbonImmutable::parse('2026-11-19 10:00:00')->addMinutes($number),
        [],
    )));
    $this->actingAs(SearchReplaceFixtures::superUser());

    expect(data_get(searchReplacePageProps(), 'batches.*.id'))
        ->toBe(array_map(fn (int $number): string => sprintf('9d3c1f2a-0b4e-4c6d-8e7f-%012d', $number), range(11, 2)));
});

it('lists a pending batch and an applied one with the items the command skipped', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team']);
    SearchReplaceFixtures::entry('ohne-titel', []);
    SearchReplaceFixtures::globalSet('footer', ['de' => ['claim' => 'Team']]);
    searchReplaceUser('Marko Muster');
    searchReplaceAppliedBatch([
        new SkippedItem('entry', 'ueber-uns', 'de', SkipReason::WorkingCopy),
        new SkippedItem('entry', 'ohne-titel', 'de', SkipReason::Changed),
        new SkippedItem('global', 'footer', 'de', SkipReason::Changed),
        new SkippedItem('term', 'tags::alt', 'en', SkipReason::Missing),
    ], 'marko');
    resolve(BatchRepository::class)->save(new Batch(
        '9d3c1f2a-0b4e-4c6d-8e7f-000000000002',
        null,
        'Praxis',
        '',
        true,
        CarbonImmutable::parse('2026-11-19 11:00:00'),
        [],
    ));
    $this->actingAs(SearchReplaceFixtures::superUser());

    expect(searchReplacePageProps()['batches'])->toBe([
        [
            'id' => '9d3c1f2a-0b4e-4c6d-8e7f-000000000002',
            'createdAt' => '2026-11-19T11:00:00+00:00',
            'user' => null,
            'search' => 'Praxis',
            'replacement' => '',
            'pending' => true,
            'appliedAt' => null,
            'replaced' => null,
            'items' => null,
            'skipped' => [],
            'undoable' => false,
            'undo' => null,
        ],
        [
            'id' => '9d3c1f2a-0b4e-4c6d-8e7f-000000000001',
            'createdAt' => '2026-11-19T10:00:00+00:00',
            'user' => 'Marko Muster',
            'search' => 'Team',
            'replacement' => 'Praxis',
            'pending' => false,
            'appliedAt' => '2026-11-19T10:01:00+00:00',
            'replaced' => 5,
            'items' => 2,
            'skipped' => [
                [
                    'type' => 'entry',
                    'title' => 'Unser Team',
                    'editUrl' => SearchReplaceFixtures::cpUrl('collections.entries.edit', ['pages', 'ueber-uns']),
                    'site' => 'Deutsch',
                    'reason' => 'Has unpublished changes',
                ],
                [
                    'type' => 'entry',
                    'title' => 'ohne-titel',
                    'editUrl' => SearchReplaceFixtures::cpUrl('collections.entries.edit', ['pages', 'ohne-titel']),
                    'site' => 'Deutsch',
                    'reason' => 'Changed since the search',
                ],
                [
                    'type' => 'global',
                    'title' => 'Footer',
                    'editUrl' => SearchReplaceFixtures::cpUrl('globals.variables.edit', ['footer', 'site' => 'de']),
                    'site' => 'Deutsch',
                    'reason' => 'Changed since the search',
                ],
                [
                    'type' => 'term',
                    'title' => 'Missing term',
                    'editUrl' => null,
                    'site' => 'English',
                    'reason' => 'Deleted',
                ],
            ],
            'undoable' => false,
            'undo' => null,
        ],
    ]);
});

it('names the user of a batch by :dataset', function (?string $name, string $user, ?string $expected): void {
    searchReplaceUser($name);
    searchReplaceAppliedBatch([], $user);
    $this->actingAs(SearchReplaceFixtures::superUser());

    expect(searchReplacePageProps()['batches'])->toHaveKey('0.user', $expected);
})->with([
    'the name' => ['Marko Muster', 'marko', 'Marko Muster'],
    'the email without a name' => [null, 'marko', 'marko@example.com'],
    'nothing once the user is deleted' => [null, 'geloescht', null],
]);

it('marks the skipped items that no longer exist as missing', function (): void {
    SearchReplaceFixtures::globalSet('footer', ['de' => ['claim' => 'Team']]);
    SearchReplaceFixtures::term('news', ['de' => ['title' => 'News']]);
    $this->actingAs(SearchReplaceFixtures::superUser());

    expect(searchReplacePageSkipped(
        new SkippedItem('entry', 'geloescht', 'de', SkipReason::Missing),
        new SkippedItem('term', 'tags::alt', 'de', SkipReason::Missing),
        new SkippedItem('global', 'header', 'de', SkipReason::Missing),
        new SkippedItem('global', 'footer', 'en', SkipReason::Missing),
        new SkippedItem('term', 'tags::news', 'de', SkipReason::Changed),
    ))->toBe([
        ['type' => 'entry', 'title' => 'Missing entry', 'editUrl' => null, 'site' => 'Deutsch', 'reason' => 'Deleted'],
        ['type' => 'term', 'title' => 'Missing term', 'editUrl' => null, 'site' => 'Deutsch', 'reason' => 'Deleted'],
        ['type' => 'global', 'title' => 'Missing global set', 'editUrl' => null, 'site' => 'Deutsch', 'reason' => 'Deleted'],
        ['type' => 'global', 'title' => 'Missing global set', 'editUrl' => null, 'site' => 'English', 'reason' => 'Deleted'],
        [
            'type' => 'term',
            'title' => 'News',
            'editUrl' => SearchReplaceFixtures::cpUrl('taxonomies.terms.edit', ['tags', 'news', 'de']),
            'site' => 'Deutsch',
            'reason' => 'Changed since the search',
        ],
    ]);
});

it('tells which skipped items could not be saved and which the user may not edit', function (): void {
    $this->actingAs(SearchReplaceFixtures::superUser());

    expect(data_get(searchReplacePageSkipped(
        new SkippedItem('entry', 'geloescht', 'de', SkipReason::Failed),
        new SkippedItem('entry', 'geloescht', 'de', SkipReason::NotPermitted),
    ), '*.reason'))->toBe(['Could not be saved', 'Not allowed to edit']);
});

it('shows the handle of a site that no longer exists', function (): void {
    $this->actingAs(SearchReplaceFixtures::superUser());

    expect(searchReplacePageSkipped(new SkippedItem('entry', 'geloescht', 'fr', SkipReason::Missing)))
        ->toHaveKey('0.site', 'fr');
});

it('leaves the site out while the project has one site', function (): void {
    Site::setSites(['de' => ['name' => 'Deutsch', 'url' => '/', 'locale' => 'de_CH']]);
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Team']);
    $this->actingAs(SearchReplaceFixtures::superUser());

    expect(searchReplacePageProps('search=team')['results'])->toHaveKey('items.0.site', null)
        ->and(searchReplacePageSkipped(new SkippedItem('entry', 'ueber-uns', 'de', SkipReason::Changed)))->toHaveKey('0.site', null);
});

it('tells whether the user may undo a batch :dataset', function (Batch $batch, User $user, bool $undoable): void {
    resolve(BatchRepository::class)->save($batch);
    $this->actingAs($user);

    expect(searchReplacePageProps()['batches'])->toHaveKey('0.undoable', $undoable);
})->with([
    'that the user made' => [fn (): Batch => searchReplaceWrittenBatch(), fn (): User => searchReplacePageUser('marko'), true],
    'that another user made, as a super user' => [fn (): Batch => searchReplaceWrittenBatch(), fn (): User => searchReplacePageUser('admin-1', super: true), true],
    'not that another user made' => [fn (): Batch => searchReplaceWrittenBatch(), fn (): User => searchReplacePageUser('editor-2'), false],
    'not while it is pending' => [
        fn (): Batch => new Batch('9d3c1f2a-0b4e-4c6d-8e7f-000000000001', 'marko', 'Team', 'Praxis', false, CarbonImmutable::now(), []),
        fn (): User => searchReplacePageUser('marko'),
        false,
    ],
    'not once its undo is asked for' => [
        fn (): Batch => searchReplaceWrittenBatch(new Undo('marko', CarbonImmutable::parse('2026-11-19 11:00:00'))),
        fn (): User => searchReplacePageUser('marko'),
        false,
    ],
]);

it('lists the undo of a batch while it is pending', function (): void {
    searchReplaceUser('Marko Muster');
    resolve(BatchRepository::class)->save(searchReplaceWrittenBatch(new Undo('marko', CarbonImmutable::parse('2026-11-19 11:00:00'))));
    $this->actingAs(SearchReplaceFixtures::superUser());

    expect(searchReplacePageProps()['batches'])->toHaveKey('0.undo', [
        'requestedAt' => '2026-11-19T11:00:00+00:00',
        'user' => 'Marko Muster',
        'pending' => true,
        'undoneAt' => null,
        'restored' => null,
        'skipped' => [],
    ]);
});

it('lists the undo of a batch once it is done, with the items it skipped', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team']);
    SearchReplaceFixtures::globalSet('footer', ['en' => ['claim' => 'Team']]);
    resolve(BatchRepository::class)->save(searchReplaceWrittenBatch(new Undo(null, CarbonImmutable::parse('2026-11-19 11:00:00'), new UndoResult(
        CarbonImmutable::parse('2026-11-19 11:01:00'),
        2,
        [
            new SkippedItem('entry', 'ueber-uns', 'de', SkipReason::Changed),
            new SkippedItem('global', 'footer', 'en', SkipReason::WorkingCopy),
            new SkippedItem('term', 'tags::alt', 'de', SkipReason::Missing),
        ],
    ))));
    $this->actingAs(SearchReplaceFixtures::superUser());

    expect(searchReplacePageProps()['batches'])->toHaveKey('0.undo', [
        'requestedAt' => '2026-11-19T11:00:00+00:00',
        'user' => null,
        'pending' => false,
        'undoneAt' => '2026-11-19T11:01:00+00:00',
        'restored' => 2,
        'skipped' => [
            [
                'type' => 'entry',
                'title' => 'Unser Team',
                'editUrl' => SearchReplaceFixtures::cpUrl('collections.entries.edit', ['pages', 'ueber-uns']),
                'site' => 'Deutsch',
                'reason' => 'Changed since the replacement',
            ],
            [
                'type' => 'global',
                'title' => 'Footer',
                'editUrl' => SearchReplaceFixtures::cpUrl('globals.variables.edit', ['footer', 'site' => 'en']),
                'site' => 'English',
                'reason' => 'Has unpublished changes',
            ],
            ['type' => 'term', 'title' => 'Missing term', 'editUrl' => null, 'site' => 'Deutsch', 'reason' => 'Deleted'],
        ],
    ]);
});
