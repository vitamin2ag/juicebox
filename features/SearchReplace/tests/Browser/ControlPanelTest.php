<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Batch;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\BatchRepository;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Patch;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Result;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\SkippedItem;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\SkipReason;
use Vitamin2\Juicebox\Features\SearchReplace\Tests\__Fixtures__\SearchReplaceFixtures;

beforeAll(function (): void {
    putenv('JUICEBOX_SEARCH_REPLACE_ENABLED=true');
});

afterAll(function (): void {
    putenv('JUICEBOX_SEARCH_REPLACE_ENABLED');
});

/**
 * Statamic's AddonTestCase switches Vite off, which leaves the control panel without its scripts. `composer build`
 * publishes the builds of the workbench, which boots with Search & Replace off, so the test publishes this one itself.
 */
beforeEach(function (): void {
    Storage::fake('local');
    config(['statamic.editions.pro' => true]);
    SearchReplaceFixtures::sites();
    SearchReplaceFixtures::blueprints();
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team', 'lead' => 'Das Team stellt sich vor.']);
    Artisan::call('vendor:publish', ['--tag' => 'search-replace', '--force' => true]);
    $this->withVite();
    $this->actingAs(SearchReplaceFixtures::superUser());
});

it('lists the matches of a search with their context and the replacement', function (): void {
    visit(SearchReplaceFixtures::cpUrl('utilities.search-replace'))
        ->fill('search', 'Team')
        ->fill('replacement', 'Praxis')
        ->keys('search', 'Enter')
        ->assertSee('Das TeamPraxis stellt sich vor.')
        ->assertSee('Unser TeamPraxis')
        ->assertPresent('a[href$="/cp/collections/pages/entries/ueber-uns"]')
        ->assertCount('del:has-text("Team")', 2)
        ->assertCount('ins:has-text("Praxis")', 2)
        ->assertNoJavaScriptErrors();
});

it('saves the selected matches as a batch and lists it as pending', function (): void {
    visit(SearchReplaceFixtures::cpUrl('utilities.search-replace', ['search' => 'Team']))
        ->fill('replacement', 'Praxis')
        ->click('Lead')
        ->press('Replace selected')
        ->press('Replace')
        ->assertSee('1 match will be replaced at the next scheduled run.')
        ->assertSee('Pending')
        ->assertNoJavaScriptErrors();

    expect(resolve(BatchRepository::class)->pending()->sole()->patches)->toEqual([
        new Patch('entry', 'ueber-uns', 'de', 'lead', 'Das Team stellt sich vor.', 'Das Praxis stellt sich vor.', 1),
    ]);
});

it('lists a match across Bard formatting without a checkbox, to be edited by hand, and shows the text around a bold match', function (): void {
    SearchReplaceFixtures::entry('kontakt', ['title' => 'Kontakt', 'content' => [['type' => 'paragraph', 'content' => [
        ['type' => 'text', 'text' => 'Ein starkes '],
        ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'Team'],
        ['type' => 'text', 'text' => ' für Sie'],
    ]]]]);

    visit(SearchReplaceFixtures::cpUrl('utilities.search-replace', ['search' => 'starkes Team']))
        ->assertSee('Matches across formatting, such as a bold word or a link, cannot be replaced here.')
        ->assertSee('Ein starkes Team für Sie')
        ->assertCount('mark:has-text("starkes Team")', 1)
        ->assertCount('[role="checkbox"]', 1)
        ->assertPresent('a[href$="/cp/collections/pages/entries/kontakt"]:has-text("Edit by hand")')
        ->assertNoJavaScriptErrors();

    visit(SearchReplaceFixtures::cpUrl('utilities.search-replace', ['search' => 'Team']))
        ->fill('replacement', 'Praxis')
        ->assertSee('Ein starkes TeamPraxis für Sie')
        ->assertCount('[role="checkbox"]', 4)
        ->assertDontSee('Edit by hand')
        ->assertNoJavaScriptErrors();
});

it('says that no text field contains the search term', function (): void {
    visit(SearchReplaceFixtures::cpUrl('utilities.search-replace', ['search' => 'Praxis']))
        ->assertSee('No text field contains the search term.')
        ->assertNoJavaScriptErrors();
});

it('lists an applied batch with the items the command skipped', function (): void {
    resolve(BatchRepository::class)->save(new Batch(
        '9d3c1f2a-0b4e-4c6d-8e7f-000000000001',
        null,
        'Team',
        'Praxis',
        false,
        CarbonImmutable::parse('2026-11-19 10:00:00'),
        [],
        new Result(CarbonImmutable::parse('2026-11-19 10:01:00'), 3, 1, [
            new SkippedItem('entry', 'ueber-uns', 'de', SkipReason::Changed),
            new SkippedItem('entry', 'geloescht', 'en', SkipReason::Missing),
        ]),
    ));

    visit(SearchReplaceFixtures::cpUrl('utilities.search-replace'))
        ->assertSee('Applied')
        ->assertSee('Changed since the search')
        ->assertSee('Missing entry')
        ->assertSee('English')
        ->assertPresent('a[href$="/cp/collections/pages/entries/ueber-uns"]')
        ->assertNoJavaScriptErrors();
});

it('undoes an applied batch at the next run and lists the item changed since', function (): void {
    $kontakt = SearchReplaceFixtures::entry('kontakt', ['title' => 'Kontakt', 'lead' => 'Schreib dem Team.']);
    resolve(BatchRepository::class)->save(new Batch(
        '9d3c1f2a-0b4e-4c6d-8e7f-000000000002',
        null,
        'Team',
        'Praxis',
        false,
        CarbonImmutable::now(),
        [
            new Patch('entry', 'ueber-uns', 'de', 'lead', 'Das Team stellt sich vor.', 'Das Praxis stellt sich vor.', 1),
            new Patch('entry', 'kontakt', 'de', 'lead', 'Schreib dem Team.', 'Schreib dem Praxis.', 1),
        ],
    ));
    Artisan::call('statamic:juicebox:search-replace:apply');
    $kontakt->set('lead', 'Schreib uns.');
    $kontakt->save();

    $page = visit(SearchReplaceFixtures::cpUrl('utilities.search-replace'))
        ->assertSee('Applied')
        ->click('td button:has-text("Undo")')
        ->click('[role="dialog"] button:has-text("Undo")')
        ->assertSee('The batch will be undone at the next scheduled run.')
        ->assertSee('Undo pending');

    Artisan::call('statamic:juicebox:search-replace:apply');

    $page->wait(3)
        ->assertSee('Partly undone')
        ->assertSee('Not undone')
        ->assertSee('Changed since the replacement')
        ->assertPresent('a[href$="/cp/collections/pages/entries/kontakt"]')
        ->assertNotPresent('td button:has-text("Undo")')
        ->assertNoJavaScriptErrors();

    expect(SearchReplaceFixtures::values('entry', 'ueber-uns'))->toMatchArray(['lead' => 'Das Team stellt sich vor.'])
        ->and(SearchReplaceFixtures::values('entry', 'kontakt'))->toMatchArray(['lead' => 'Schreib uns.']);
});
