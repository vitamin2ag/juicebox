<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Statamic\Contracts\Auth\User;
use Vitamin2\Juicebox\Features\SearchReplace\Data\Occurrence;
use Vitamin2\Juicebox\Features\SearchReplace\Http\Controllers\ReplaceController;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Batch;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\BatchRepository;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Patch;
use Vitamin2\Juicebox\Features\SearchReplace\Rules\ValidUtf8;
use Vitamin2\Juicebox\Features\SearchReplace\Searching\Searcher;
use Vitamin2\Juicebox\Features\SearchReplace\ServiceProvider;
use Vitamin2\Juicebox\Features\SearchReplace\Tests\__Fixtures__\SearchReplaceFixtures;

covers(ReplaceController::class, ValidUtf8::class, ServiceProvider::class);

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
    $this->travelTo(CarbonImmutable::parse('2026-11-19 10:00:00'));
});

/**
 * The ids of the occurrences a super user finds, in the order the search finds them.
 *
 * @return array<int, string>
 */
function searchReplaceSelectable(string $term, bool $matchCase = false): array
{
    return resolve(Searcher::class)->search($term, SearchReplaceFixtures::superUser(), $matchCase)->occurrences
        ->map(fn (Occurrence $occurrence): string => $occurrence->id())
        ->all();
}

/**
 * @param  array<mixed>  $data
 * @return TestResponse<Illuminate\Http\Response>
 */
function searchReplacePost(array $data, ?User $user = null): TestResponse
{
    return test()
        ->actingAs($user ?? SearchReplaceFixtures::superUser())
        ->post(SearchReplaceFixtures::cpUrl('utilities.search-replace.replace'), $data);
}

/**
 * The only batch the control panel saved.
 */
function searchReplaceSavedBatch(): Batch
{
    return resolve(BatchRepository::class)->latest(10)->sole();
}

/**
 * The items and new values of the patches of the only batch.
 *
 * @return array<int, string>
 */
function searchReplaceSavedPatches(): array
{
    return array_map(fn (Patch $patch): string => "{$patch->id} {$patch->path}: {$patch->after}", searchReplaceSavedBatch()->patches);
}

/**
 * A request that saves a batch of the one match in the entry it saves.
 *
 * @return array<string, mixed>
 */
function searchReplaceValidRequest(): array
{
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team']);

    return ['search' => 'Team', 'replacement' => 'Praxis', 'match_case' => false, 'occurrences' => searchReplaceSelectable('team')];
}

it('saves the selected matches as a pending batch and goes back to the utility', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team', 'lead' => 'Das Team im Team']);
    [$title, , $lead] = searchReplaceSelectable('team');
    $user = SearchReplaceFixtures::superUser();

    searchReplacePost(['search' => 'team', 'replacement' => 'Praxis', 'match_case' => false, 'occurrences' => [$title, $lead]], $user)
        ->assertRedirect(SearchReplaceFixtures::cpUrl('utilities.search-replace'))
        ->assertSessionHas('success', '2 matches will be replaced at the next scheduled run.');

    $batch = searchReplaceSavedBatch();

    expect(Str::isUuid($batch->id))->toBeTrue()
        ->and(Arr::except($batch->toArray(), 'id'))->toBe([
            'user' => $user->id(),
            'search' => 'team',
            'replacement' => 'Praxis',
            'match_case' => false,
            'created_at' => '2026-11-19T10:00:00+00:00',
            'patches' => [
                ['type' => 'entry', 'id' => 'ueber-uns', 'site' => 'de', 'path' => 'title', 'before' => 'Unser Team', 'after' => 'Unser Praxis', 'occurrences' => 1],
                ['type' => 'entry', 'id' => 'ueber-uns', 'site' => 'de', 'path' => 'lead', 'before' => 'Das Team im Team', 'after' => 'Das Team im Praxis', 'occurrences' => 1],
            ],
            'result' => null,
        ]);
});

it('keeps :dataset as the user typed it, which the control panel would trim', function (string $search, string $replacement, string $after): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Das  Team im Team']);

    searchReplacePost(['search' => $search, 'replacement' => $replacement, 'occurrences' => searchReplaceSelectable($search)])
        ->assertSessionHasNoErrors();

    $batch = searchReplaceSavedBatch();

    expect($batch->search)->toBe($search)
        ->and($batch->replacement)->toBe($replacement)
        ->and(searchReplaceSavedPatches())->toBe(["ueber-uns title: {$after}"]);
})->with([
    'a double space' => ['  ', ' ', 'Das Team im Team'],
    'a search that starts with a space and an empty replacement' => [' Team', '', 'Das  im'],
    'a replacement that ends with a space' => ['Team', 'Praxis ', 'Das  Praxis  im Praxis '],
]);

it('searches again with match case', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Team team']);

    searchReplacePost(['search' => 'team', 'replacement' => 'Crew', 'match_case' => true, 'occurrences' => searchReplaceSelectable('team', true)]);

    expect(searchReplaceSavedBatch()->matchCase)->toBeTrue()
        ->and(searchReplaceSavedPatches())->toBe(['ueber-uns title: Team Crew']);
});

it('leaves out the matches in items the user may not edit', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Team']);
    SearchReplaceFixtures::entry('news-1', ['title' => 'Team'], collection: 'news');
    $user = SearchReplaceFixtures::userWith('access search_replace utility', 'access de site', 'edit pages entries');

    searchReplacePost(['search' => 'team', 'replacement' => 'Praxis', 'occurrences' => searchReplaceSelectable('team')], $user)
        ->assertSessionHas('success', '1 match will be replaced at the next scheduled run.');

    expect(searchReplaceSavedPatches())->toBe(['ueber-uns title: Praxis']);
});

it('leaves out a match whose field changed after the search', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team']);
    SearchReplaceFixtures::entry('kontakt', ['title' => 'Kontakt zum Team']);
    $selected = searchReplaceSelectable('team');
    SearchReplaceFixtures::entry('kontakt', ['title' => 'Kontakt zum neuen Team']);

    searchReplacePost(['search' => 'team', 'replacement' => 'Praxis', 'occurrences' => $selected]);

    expect(searchReplaceSavedPatches())->toBe(['ueber-uns title: Unser Praxis']);
});

it('saves no batch when :dataset', function (Closure $select): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team']);

    searchReplacePost(['search' => 'team', 'replacement' => 'Praxis', 'occurrences' => $select()])
        ->assertSessionHasErrors(['occurrences' => 'The selected matches have changed since the search. Search again.']);

    expect(Storage::disk('local')->allFiles())->toBeEmpty();
})->with([
    'every selected match changed' => function (): array {
        $selected = searchReplaceSelectable('team');
        SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser neues Team']);

        return $selected;
    },
    'no selected match is known' => fn (): array => ['unbekannt'],
]);

/*
 * Statamic's messages of the control panel, which tell each rule apart from the error of a selection that is gone.
 */
it('rejects :dataset', function (array $data, string $field, string $message): void {
    searchReplacePost($data)->assertSessionHasErrors([$field => $message]);

    expect(Storage::disk('local')->allFiles())->toBeEmpty();
})->with([
    'no search' => fn (): array => [Arr::except(searchReplaceValidRequest(), 'search'), 'search', 'Must be present.'],
    'an empty search' => fn (): array => [[...searchReplaceValidRequest(), 'search' => ''], 'search', 'Must be a string.'],
    'a list as search' => fn (): array => [[...searchReplaceValidRequest(), 'search' => ['Team']], 'search', 'Must be a string.'],
    'no replacement' => fn (): array => [Arr::except(searchReplaceValidRequest(), 'replacement'), 'replacement', 'Must be present.'],
    'a list as replacement' => fn (): array => [[...searchReplaceValidRequest(), 'replacement' => ['Praxis']], 'replacement', 'Must be a string.'],
    'a match case that is no boolean' => fn (): array => [[...searchReplaceValidRequest(), 'match_case' => 'vielleicht'], 'match_case', 'Must be true or false.'],
    'no selection' => fn (): array => [Arr::except(searchReplaceValidRequest(), 'occurrences'), 'occurrences', 'This field is required.'],
    'an empty selection' => fn (): array => [[...searchReplaceValidRequest(), 'occurrences' => []], 'occurrences', 'This field is required.'],
    'a selection that is no list' => fn (): array => [[...searchReplaceValidRequest(), 'occurrences' => 'alle'], 'occurrences', 'Must be an array.'],
    'a selection of lists' => fn (): array => [[...searchReplaceValidRequest(), 'occurrences' => [['id']]], 'occurrences.0', 'Must be a string.'],
]);

it('saves the batch of the valid request the rejected ones change', function (): void {
    searchReplacePost(searchReplaceValidRequest())->assertSessionHasNoErrors();

    expect(searchReplaceSavedPatches())->toBe(['ueber-uns title: Unser Praxis']);
});

it('rejects a search that is not valid UTF-8', function (): void {
    searchReplacePost(['search' => "Te\xFFam", 'replacement' => 'Praxis', 'occurrences' => ['unbekannt']])
        ->assertSessionHasErrors(['search' => 'The search term is not valid UTF-8.']);

    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('forbids replacing without the access search_replace utility permission', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team']);
    $selected = searchReplaceSelectable('team');

    $this->actingAs(SearchReplaceFixtures::userWith('access de site', 'edit pages entries'))
        ->postJson(SearchReplaceFixtures::cpUrl('utilities.search-replace.replace'), [
            'search' => 'team',
            'replacement' => 'Praxis',
            'occurrences' => $selected,
        ])
        ->assertForbidden();

    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});
