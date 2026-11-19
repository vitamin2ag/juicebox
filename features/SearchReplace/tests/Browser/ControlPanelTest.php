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
