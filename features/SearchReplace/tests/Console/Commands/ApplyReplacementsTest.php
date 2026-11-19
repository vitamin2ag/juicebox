<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Vitamin2\Juicebox\Features\SearchReplace\Console\Commands\ApplyReplacements;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\BatchRepository;
use Vitamin2\Juicebox\Features\SearchReplace\Tests\__Fixtures__\SearchReplaceFixtures;

covers(ApplyReplacements::class);

beforeEach(function (): void {
    SearchReplaceFixtures::sites();
    SearchReplaceFixtures::blueprints();
    config(['juicebox.search_replace.enabled' => true]);
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-11-19 10:01:00'));
});

function searchReplaceBatchNumber(int $number): string
{
    return sprintf('9d3c1f2a-0b4e-4c6d-8e7f-%012d', $number);
}

/**
 * The batch the command saved, as its JSON holds it.
 */
function searchReplaceBatchFile(int $number): mixed
{
    return json_decode(Storage::disk('local')->get('juicebox/search-replace/'.searchReplaceBatchNumber($number).'.json') ?? '', true);
}

it('applies a pending batch to the content and saves it as applied', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team', 'lead' => 'Das Team im Team']);
    resolve(BatchRepository::class)->save(SearchReplaceFixtures::batch(id: searchReplaceBatchNumber(1)));

    $status = Artisan::call('statamic:juicebox:search-replace:apply');

    expect($status)->toBe(0)
        ->and(Artisan::output())->toBe("Applied 1 batch, replaced 3 matches, skipped 0 items.\n")
        ->and(SearchReplaceFixtures::savedEntry('ueber-uns'))->toMatchArray(['title' => 'Unser Praxis', 'lead' => 'Das Praxis im Praxis'])
        ->and(searchReplaceBatchFile(1))->toMatchArray([
            'id' => searchReplaceBatchNumber(1),
            'search' => 'Team',
            'replacement' => 'Praxis',
            'result' => ['applied_at' => '2026-11-19T10:01:00+00:00', 'replaced' => 3, 'items' => 1, 'skipped' => []],
        ]);
});

it('applies the pending batches from the oldest to the newest and saves each one', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team']);
    SearchReplaceFixtures::entry('kontakt', ['title' => 'Kontakt zum Team']);
    $batches = resolve(BatchRepository::class);
    $batches->save(SearchReplaceFixtures::batch(replacement: 'Crew', id: searchReplaceBatchNumber(1), createdAt: '2026-11-19 10:00:30'));
    $batches->save(SearchReplaceFixtures::batch(replacement: 'Praxis', id: searchReplaceBatchNumber(2), createdAt: '2026-11-19 10:00:00'));

    Artisan::call('statamic:juicebox:search-replace:apply');

    expect(Artisan::output())->toBe("Applied 2 batches, replaced 2 matches, skipped 2 items.\n")
        ->and(SearchReplaceFixtures::values('entry', 'ueber-uns'))->toBe(['title' => 'Unser Praxis'])
        ->and(SearchReplaceFixtures::values('entry', 'kontakt'))->toBe(['title' => 'Kontakt zum Praxis'])
        ->and(data_get(searchReplaceBatchFile(2), 'result.replaced'))->toBe(2)
        ->and(data_get(searchReplaceBatchFile(1), 'result.skipped.*.reason'))->toBe(['changed', 'changed'])
        ->and($batches->pending())->toBeEmpty();
});

it('leaves the applied batches as they are', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team']);
    $batches = resolve(BatchRepository::class);
    $batches->save(SearchReplaceFixtures::batch(id: searchReplaceBatchNumber(1)));
    Artisan::call('statamic:juicebox:search-replace:apply');
    $applied = searchReplaceBatchFile(1);
    $this->travelTo(CarbonImmutable::parse('2026-11-19 10:02:00'));

    $status = Artisan::call('statamic:juicebox:search-replace:apply');

    expect($status)->toBe(0)
        ->and(Artisan::output())->toBe("Applied 0 batches, replaced 0 matches, skipped 0 items.\n")
        ->and(searchReplaceBatchFile(1))->toBe($applied);
});

it('applies nothing while the feature is disabled', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team']);
    resolve(BatchRepository::class)->save(SearchReplaceFixtures::batch(id: searchReplaceBatchNumber(1)));
    config(['juicebox.search_replace.enabled' => false]);

    $status = Artisan::call('statamic:juicebox:search-replace:apply');

    expect($status)->toBe(1)
        ->and(Artisan::output())->toBe("Search & Replace is disabled: juicebox.search_replace.enabled is false.\n")
        ->and(SearchReplaceFixtures::values('entry', 'ueber-uns'))->toBe(['title' => 'Unser Team'])
        ->and(resolve(BatchRepository::class)->pending())->toHaveCount(1);
});
