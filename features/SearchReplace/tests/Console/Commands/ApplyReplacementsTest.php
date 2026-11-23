<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalVariables;
use Statamic\Facades\Stache;
use Statamic\Facades\Term;
use Statamic\Taxonomies\LocalizedTerm;
use Vitamin2\Juicebox\Features\SearchReplace\Console\Commands\ApplyReplacements;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Batch;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\BatchRepository;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Undo;
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

/**
 * Asks for the undo of the saved batch, as the control panel does for a user without an id.
 */
function searchReplaceAskForUndo(int $number): void
{
    $batches = resolve(BatchRepository::class);
    $batch = $batches->find(searchReplaceBatchNumber($number));

    if ($batch instanceof Batch) {
        $batches->save($batch->withUndo(new Undo(null, CarbonImmutable::now())));
    }
}

/**
 * The contents of the files of the entries, the term and the global set the command tests change.
 *
 * @return array<string, string>
 */
function searchReplaceContentFiles(): array
{
    $term = Term::find('tags::news');

    return collect([
        Entry::find('ueber-uns')?->path(),
        Entry::find('about')?->path(),
        $term instanceof LocalizedTerm ? $term->path() : null,
        GlobalVariables::find('footer::de')?->path(),
        GlobalVariables::find('footer::en')?->path(),
    ])
        ->mapWithKeys(fn (mixed $path): array => is_string($path) ? [$path => File::get($path)] : [])
        ->all();
}

it('applies a pending batch to the content and saves it as applied', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team', 'lead' => 'Das Team im Team']);
    resolve(BatchRepository::class)->save(SearchReplaceFixtures::batch(id: searchReplaceBatchNumber(1)));

    $status = Artisan::call('statamic:juicebox:search-replace:apply');

    expect($status)->toBe(0)
        ->and(Artisan::output())->toBe("Applied 1 batch, replaced 3 matches, skipped 0 items.\nUndid 0 batches, restored 0 items, skipped 0 items.\n")
        ->and(SearchReplaceFixtures::savedEntry('ueber-uns'))->toMatchArray(['title' => 'Unser Praxis', 'lead' => 'Das Praxis im Praxis'])
        ->and(searchReplaceBatchFile(1))->toMatchArray([
            'id' => searchReplaceBatchNumber(1),
            'search' => 'Team',
            'replacement' => 'Praxis',
            'result' => [
                'applied_at' => '2026-11-19T10:01:00+00:00',
                'replaced' => 3,
                'items' => 1,
                'skipped' => [],
                'written' => [[
                    'type' => 'entry',
                    'id' => 'ueber-uns',
                    'site' => 'de',
                    'fields' => [
                        ['handle' => 'title', 'before' => 'Unser Team', 'after' => 'Unser Praxis'],
                        ['handle' => 'lead', 'before' => 'Das Team im Team', 'after' => 'Das Praxis im Praxis'],
                    ],
                ]],
            ],
            'undo' => null,
        ]);
});

it('applies the pending batches from the oldest to the newest and saves each one', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team']);
    SearchReplaceFixtures::entry('kontakt', ['title' => 'Kontakt zum Team']);
    $batches = resolve(BatchRepository::class);
    $batches->save(SearchReplaceFixtures::batch(replacement: 'Crew', id: searchReplaceBatchNumber(1), createdAt: '2026-11-19 10:00:30'));
    $batches->save(SearchReplaceFixtures::batch(replacement: 'Praxis', id: searchReplaceBatchNumber(2), createdAt: '2026-11-19 10:00:00'));

    Artisan::call('statamic:juicebox:search-replace:apply');

    expect(Artisan::output())->toBe("Applied 2 batches, replaced 2 matches, skipped 2 items.\nUndid 0 batches, restored 0 items, skipped 0 items.\n")
        ->and(SearchReplaceFixtures::values('entry', 'ueber-uns'))->toBe(['title' => 'Unser Praxis'])
        ->and(SearchReplaceFixtures::values('entry', 'kontakt'))->toBe(['title' => 'Kontakt zum Praxis'])
        ->and(data_get(searchReplaceBatchFile(2), 'result.replaced'))->toBe(2)
        ->and(data_get(searchReplaceBatchFile(1), 'result.skipped.*.reason'))->toBe(['changed', 'changed'])
        ->and($batches->pending())->toBeEmpty();
});

it('goes on with the other items and the later batches of a run when a save throws', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team']);
    SearchReplaceFixtures::entry('kontakt', ['title' => 'Kontakt zum Team']);
    $batches = resolve(BatchRepository::class);
    $batches->save(SearchReplaceFixtures::batch(id: searchReplaceBatchNumber(1), createdAt: '2026-11-19 10:00:00'));
    $batches->save(SearchReplaceFixtures::batch(replacement: 'Crew', id: searchReplaceBatchNumber(2), createdAt: '2026-11-19 10:00:30'));
    Exceptions::fake();
    Event::listen(EntrySaving::class, function (EntrySaving $event): void {
        if ($event->entry->get('title') === 'Unser Praxis') {
            throw new RuntimeException('The disk is full.');
        }
    });

    $status = Artisan::call('statamic:juicebox:search-replace:apply');

    expect($status)->toBe(0)
        ->and(Artisan::output())->toBe("Applied 2 batches, replaced 2 matches, skipped 2 items.\nUndid 0 batches, restored 0 items, skipped 0 items.\n")
        ->and(SearchReplaceFixtures::values('entry', 'ueber-uns'))->toBe(['title' => 'Unser Crew'])
        ->and(SearchReplaceFixtures::values('entry', 'kontakt'))->toBe(['title' => 'Kontakt zum Praxis'])
        ->and(data_get(searchReplaceBatchFile(1), 'result.skipped'))->toBe([['type' => 'entry', 'id' => 'ueber-uns', 'site' => 'de', 'reason' => 'failed']])
        ->and(data_get(searchReplaceBatchFile(2), 'result.skipped'))->toBe([['type' => 'entry', 'id' => 'kontakt', 'site' => 'de', 'reason' => 'changed']])
        ->and($batches->pending())->toBeEmpty();

    Exceptions::assertReported(RuntimeException::class);
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
        ->and(Artisan::output())->toBe("Applied 0 batches, replaced 0 matches, skipped 0 items.\nUndid 0 batches, restored 0 items, skipped 0 items.\n")
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

it('undoes an applied batch at the next run, which gives the files their contents from before the batch back', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', SearchReplaceFixtures::data('page'));
    SearchReplaceFixtures::entry('about', ['title' => 'Our team', 'lead' => "A team\n  of five:\n", 'notes' => 'intern'], 'en', 'ueber-uns');
    SearchReplaceFixtures::term('news', ['de' => ['title' => 'News', 'description' => 'Neues vom Team.'], 'en' => ['description' => 'Team news']]);
    SearchReplaceFixtures::globalSet('footer', ['de' => ['claim' => 'Ihr Team'], 'en' => ['claim' => 'Your team']]);
    $files = searchReplaceContentFiles();
    resolve(BatchRepository::class)->save(SearchReplaceFixtures::batch(replacement: 'Praxis & Co', id: searchReplaceBatchNumber(1)));
    Artisan::call('statamic:juicebox:search-replace:apply');
    $applied = searchReplaceContentFiles();
    searchReplaceAskForUndo(1);
    Stache::clear();
    $this->travelTo(CarbonImmutable::parse('2026-11-19 11:01:00'));

    $status = Artisan::call('statamic:juicebox:search-replace:apply');

    expect($status)->toBe(0)
        ->and(Artisan::output())->toBe("Applied 0 batches, replaced 0 matches, skipped 0 items.\nUndid 1 batch, restored 6 items, skipped 0 items.\n")
        ->and($applied)->not->toBe($files)
        ->and(searchReplaceContentFiles())->toBe($files)
        ->and(searchReplaceBatchFile(1))->toMatchArray([
            'undo' => [
                'user' => null,
                'requested_at' => '2026-11-19T10:01:00+00:00',
                'result' => ['undone_at' => '2026-11-19T11:01:00+00:00', 'restored' => 6, 'skipped' => []],
            ],
        ]);
});

it('partly undoes a batch whose items changed, were deleted or got a working copy since', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team']);
    $changed = SearchReplaceFixtures::entry('kontakt', ['title' => 'Kontakt zum Team']);
    $deleted = SearchReplaceFixtures::entry('team', ['title' => 'Team']);
    $drafted = SearchReplaceFixtures::entry('news', ['title' => 'Team News']);
    resolve(BatchRepository::class)->save(SearchReplaceFixtures::batch(id: searchReplaceBatchNumber(1)));
    Artisan::call('statamic:juicebox:search-replace:apply');
    $changed->set('title', 'Kontakt zur Praxis');
    $changed->save();
    $deleted->delete();
    SearchReplaceFixtures::workingCopy($drafted);
    searchReplaceAskForUndo(1);

    Artisan::call('statamic:juicebox:search-replace:apply');

    expect(Artisan::output())->toBe("Applied 0 batches, replaced 0 matches, skipped 0 items.\nUndid 1 batch, restored 1 item, skipped 3 items.\n")
        ->and(SearchReplaceFixtures::values('entry', 'ueber-uns'))->toBe(['title' => 'Unser Team'])
        ->and(SearchReplaceFixtures::values('entry', 'kontakt'))->toBe(['title' => 'Kontakt zur Praxis'])
        ->and(SearchReplaceFixtures::values('entry', 'news'))->toBe(['title' => 'Praxis News'])
        ->and(data_get(searchReplaceBatchFile(1), 'undo.result'))->toBe([
            'undone_at' => '2026-11-19T10:01:00+00:00',
            'restored' => 1,
            'skipped' => [
                ['type' => 'entry', 'id' => 'kontakt', 'site' => 'de', 'reason' => 'changed'],
                ['type' => 'entry', 'id' => 'team', 'site' => 'de', 'reason' => 'missing'],
                ['type' => 'entry', 'id' => 'news', 'site' => 'de', 'reason' => 'working_copy'],
            ],
        ]);
});

it('applies the pending batches before the undos, which skip the items those batches changed', function (): void {
    SearchReplaceFixtures::entry('ueber-uns', ['title' => 'Unser Team']);
    SearchReplaceFixtures::entry('kontakt', ['title' => 'Kontakt zum Team']);
    $batches = resolve(BatchRepository::class);
    $batches->save(SearchReplaceFixtures::batch(id: searchReplaceBatchNumber(1)));
    Artisan::call('statamic:juicebox:search-replace:apply');
    searchReplaceAskForUndo(1);
    $batches->save(SearchReplaceFixtures::batch(search: 'Unser', replacement: 'Ihr', id: searchReplaceBatchNumber(2)));

    Artisan::call('statamic:juicebox:search-replace:apply');

    expect(Artisan::output())->toBe("Applied 1 batch, replaced 1 match, skipped 0 items.\nUndid 1 batch, restored 1 item, skipped 1 item.\n")
        ->and(SearchReplaceFixtures::values('entry', 'ueber-uns'))->toBe(['title' => 'Ihr Praxis'])
        ->and(SearchReplaceFixtures::values('entry', 'kontakt'))->toBe(['title' => 'Kontakt zum Team'])
        ->and(data_get(searchReplaceBatchFile(1), 'undo.result.skipped'))->toBe([
            ['type' => 'entry', 'id' => 'ueber-uns', 'site' => 'de', 'reason' => 'changed'],
        ])
        ->and($batches->pendingUndos())->toBeEmpty();
});
