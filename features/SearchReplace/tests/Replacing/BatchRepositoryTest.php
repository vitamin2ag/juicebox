<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Batch;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\BatchRepository;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Patch;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Result;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\SkippedItem;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\SkipReason;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Undo;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\UndoResult;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\WrittenItem;

covers(BatchRepository::class, Batch::class, Patch::class, Result::class, SkippedItem::class, WrittenItem::class, Undo::class, UndoResult::class);

beforeEach(function (): void {
    Storage::fake('local');
});

function searchReplaceBatchId(int $number = 1): string
{
    return sprintf('9d3c1f2a-0b4e-4c6d-8e7f-%012d', $number);
}

function pendingSearchReplaceBatch(int $number = 1, string $createdAt = '2026-11-19 10:00:00'): Batch
{
    return new Batch(searchReplaceBatchId($number), 'editor-1', 'Team', 'Praxis', false, CarbonImmutable::parse($createdAt), [
        new Patch('entry', 'ueber-uns', 'de', 'title', 'Unser Team', 'Unser Praxis', 1),
    ]);
}

function appliedSearchReplaceBatch(int $number = 1, string $createdAt = '2026-11-19 10:00:00'): Batch
{
    return pendingSearchReplaceBatch($number, $createdAt)->applied(new Result(CarbonImmutable::parse('2026-11-19 10:01:00'), 1, 1, [], [
        new WrittenItem('entry', 'ueber-uns', 'de', [['handle' => 'title', 'before' => 'Unser Team', 'after' => 'Unser Praxis']]),
    ]));
}

function undoingSearchReplaceBatch(int $number, string $createdAt, string $requestedAt): Batch
{
    return appliedSearchReplaceBatch($number, $createdAt)->withUndo(new Undo('editor-1', CarbonImmutable::parse($requestedAt)));
}

/**
 * An applied and undone batch with one patch, one skipped item, one written item and one item the undo skipped.
 *
 * @return array<string, mixed>
 */
function searchReplaceBatchData(): array
{
    return [
        'id' => searchReplaceBatchId(),
        'user' => 'editor-1',
        'search' => 'Team',
        'replacement' => 'Praxis',
        'match_case' => true,
        'created_at' => '2026-11-19T10:00:00+00:00',
        'patches' => [[
            'type' => 'entry',
            'id' => 'ueber-uns',
            'site' => 'de',
            'path' => 'content.1.attrs.values.text',
            'before' => 'Team first',
            'after' => 'Praxis first',
            'occurrences' => 1,
        ]],
        'result' => [
            'applied_at' => '2026-11-19T10:01:00+00:00',
            'replaced' => 0,
            'items' => 0,
            'skipped' => [['type' => 'entry', 'id' => 'ueber-uns', 'site' => 'de', 'reason' => 'changed']],
            'written' => [[
                'type' => 'term',
                'id' => 'tags::news',
                'site' => 'en',
                'fields' => [
                    ['handle' => 'description', 'before' => 'Team news', 'after' => 'Praxis news'],
                    ['handle' => 'content', 'before' => [['type' => 'text', 'text' => 'Team']], 'after' => []],
                ],
            ]],
        ],
        'undo' => [
            'user' => 'admin-1',
            'requested_at' => '2026-11-19T11:00:00+00:00',
            'result' => [
                'undone_at' => '2026-11-19T11:01:00+00:00',
                'restored' => 0,
                'skipped' => [['type' => 'term', 'id' => 'tags::news', 'site' => 'en', 'reason' => 'working_copy']],
            ],
        ],
    ];
}

/**
 * The JSON of searchReplaceBatchData(), with the value at the given path replaced.
 */
function searchReplaceBatchJson(?string $path = null, mixed $value = null): string
{
    $batch = searchReplaceBatchData();

    if ($path !== null) {
        Arr::set($batch, $path, $value);
    }

    return json_encode($batch, JSON_THROW_ON_ERROR);
}

/**
 * The JSON of searchReplaceBatchData(), without the value at the given path.
 */
function searchReplaceBatchJsonWithout(string $path): string
{
    $batch = searchReplaceBatchData();
    Arr::forget($batch, $path);

    return json_encode($batch, JSON_THROW_ON_ERROR);
}

/**
 * The batch searchReplaceBatchData() holds.
 */
function searchReplaceBatchOfData(): Batch
{
    return new Batch(
        searchReplaceBatchId(),
        'editor-1',
        'Team',
        'Praxis',
        true,
        CarbonImmutable::parse('2026-11-19 10:00:00'),
        [new Patch('entry', 'ueber-uns', 'de', 'content.1.attrs.values.text', 'Team first', 'Praxis first', 1)],
        new Result(CarbonImmutable::parse('2026-11-19 10:01:00'), 0, 0, [new SkippedItem('entry', 'ueber-uns', 'de', SkipReason::Changed)], [
            new WrittenItem('term', 'tags::news', 'en', [
                ['handle' => 'description', 'before' => 'Team news', 'after' => 'Praxis news'],
                ['handle' => 'content', 'before' => [['type' => 'text', 'text' => 'Team']], 'after' => []],
            ]),
        ]),
        new Undo('admin-1', CarbonImmutable::parse('2026-11-19 11:00:00'), new UndoResult(CarbonImmutable::parse('2026-11-19 11:01:00'), 0, [
            new SkippedItem('term', 'tags::news', 'en', SkipReason::WorkingCopy),
        ])),
    );
}

it('saves a batch as JSON in juicebox/search-replace/<id>.json on the local disk', function (): void {
    resolve(BatchRepository::class)->save(new Batch(searchReplaceBatchId(), null, 'Team', 'Ärzte/Team', false, CarbonImmutable::parse('2026-11-19 10:00:00'), [
        new Patch('term', 'tags::news', 'en', 'description', 'Team news', 'Ärzte/Team news', 1),
    ]));

    expect(Storage::disk('local')->get('juicebox/search-replace/'.searchReplaceBatchId().'.json'))->toBe(<<<'JSON'
        {
            "id": "9d3c1f2a-0b4e-4c6d-8e7f-000000000001",
            "user": null,
            "search": "Team",
            "replacement": "Ärzte/Team",
            "match_case": false,
            "created_at": "2026-11-19T10:00:00+00:00",
            "patches": [
                {
                    "type": "term",
                    "id": "tags::news",
                    "site": "en",
                    "path": "description",
                    "before": "Team news",
                    "after": "Ärzte/Team news",
                    "occurrences": 1
                }
            ],
            "result": null,
            "undo": null
        }
        JSON);
});

it('converts an applied and undone batch to the values of its JSON', function (): void {
    expect(searchReplaceBatchOfData()->toArray())->toBe(searchReplaceBatchData());
});

it('finds a saved batch by its id', function (Batch $batch): void {
    $batches = resolve(BatchRepository::class);

    $batches->save($batch);

    expect($batches->find($batch->id))->toEqual($batch);
})->with([
    'pending' => fn (): Batch => pendingSearchReplaceBatch(),
    'applied' => fn (): Batch => new Batch(searchReplaceBatchId(), null, 'Team', '', true, CarbonImmutable::parse('2026-11-19 10:00:00'), [
        new Patch('entry', 'ueber-uns', 'de', 'title', 'Unser Team', 'Unser ', 1),
        new Patch('term', 'tags::news', 'en', 'description', 'Team news', ' news', 1),
        new Patch('global', 'footer', 'de', 'claim', 'Ihr Team', 'Ihr ', 1),
    ], new Result(CarbonImmutable::parse('2026-11-19 10:01:00'), 1, 1, [
        new SkippedItem('term', 'tags::news', 'en', SkipReason::Missing),
        new SkippedItem('global', 'footer', 'de', SkipReason::WorkingCopy),
    ])),
    'with a pending undo asked for by a user without an id' => fn (): Batch => appliedSearchReplaceBatch()
        ->withUndo(new Undo(null, CarbonImmutable::parse('2026-11-19 11:00:00'))),
    'undone' => searchReplaceBatchOfData(),
]);

it('reads the batch a file holds', function (): void {
    Storage::disk('local')->put('juicebox/search-replace/'.searchReplaceBatchId().'.json', searchReplaceBatchJson());

    expect(resolve(BatchRepository::class)->find(searchReplaceBatchId()))->toEqual(searchReplaceBatchOfData());
});

it('reads a written field that held nothing', function (): void {
    Storage::disk('local')->put('juicebox/search-replace/'.searchReplaceBatchId().'.json', searchReplaceBatchJson('result.written.0.fields.0.before'));

    expect(resolve(BatchRepository::class)->find(searchReplaceBatchId())?->result?->written[0]->fields[0])
        ->toBe(['handle' => 'description', 'before' => null, 'after' => 'Praxis news']);
});

it('overwrites a batch saved again, such as once it is applied', function (): void {
    $batches = resolve(BatchRepository::class);

    $batches->save(pendingSearchReplaceBatch());
    $batches->save(appliedSearchReplaceBatch());

    expect($batches->find(searchReplaceBatchId()))->toEqual(appliedSearchReplaceBatch())
        ->and(Storage::disk('local')->allFiles())->toBe(['juicebox/search-replace/'.searchReplaceBatchId().'.json']);
});

it('finds no batch :dataset', function (string $id): void {
    Storage::disk('local')->put('juicebox/search-replace/team.json', searchReplaceBatchJson());
    Storage::disk('local')->put('juicebox/team.json', searchReplaceBatchJson());

    expect(resolve(BatchRepository::class)->find($id))->toBeNull();
})->with([
    'that was never saved' => searchReplaceBatchId(2),
    'for an id that is no UUID' => 'team',
    'for a path outside the batches' => '../team',
]);

it('treats a file :dataset as missing', function (string $contents): void {
    Storage::disk('local')->put('juicebox/search-replace/'.searchReplaceBatchId().'.json', $contents);

    expect(resolve(BatchRepository::class)->find(searchReplaceBatchId()))->toBeNull()
        ->and(resolve(BatchRepository::class)->latest(10))->toBeEmpty();
})->with([
    'that is no JSON' => '{"id":',
    'that holds no object' => '"batch"',
    'without id' => searchReplaceBatchJson('id'),
    'with a user that is no id' => searchReplaceBatchJson('user', 7),
    'without search' => searchReplaceBatchJson('search'),
    'without replacement' => searchReplaceBatchJson('replacement'),
    'with a match_case that is no boolean' => searchReplaceBatchJson('match_case', 'yes'),
    'with a created_at that is no date' => searchReplaceBatchJson('created_at', 'gestern'),
    'with patches that are no list' => searchReplaceBatchJson('patches', 'title'),
    'with a patch that is no object' => searchReplaceBatchJson('patches.0', 'title'),
    'with a patch of an unknown type' => searchReplaceBatchJson('patches.0.type', 'user'),
    'with a patch without id' => searchReplaceBatchJson('patches.0.id'),
    'with a patch without site' => searchReplaceBatchJson('patches.0.site'),
    'with a patch without path' => searchReplaceBatchJson('patches.0.path'),
    'with a patch without before' => searchReplaceBatchJson('patches.0.before'),
    'with a patch without after' => searchReplaceBatchJson('patches.0.after'),
    'with a patch whose occurrences are no number' => searchReplaceBatchJson('patches.0.occurrences', '1'),
    'with a result that is no object' => searchReplaceBatchJson('result', 'applied'),
    'with a result whose applied_at is no date' => searchReplaceBatchJson('result.applied_at', 'gestern'),
    'with a result whose replaced is no number' => searchReplaceBatchJson('result.replaced', '0'),
    'with a result whose items are no number' => searchReplaceBatchJson('result.items', '0'),
    'with skipped items that are no list' => searchReplaceBatchJson('result.skipped', 'ueber-uns'),
    'with a skipped item that is no object' => searchReplaceBatchJson('result.skipped.0', 'ueber-uns'),
    'with a skipped item of an unknown type' => searchReplaceBatchJson('result.skipped.0.type', 'user'),
    'with a skipped item without id' => searchReplaceBatchJson('result.skipped.0.id'),
    'with a skipped item without site' => searchReplaceBatchJson('result.skipped.0.site'),
    'with a skipped item of an unknown reason' => searchReplaceBatchJson('result.skipped.0.reason', 'gone'),
    'without written items' => searchReplaceBatchJsonWithout('result.written'),
    'with written items that are no list' => searchReplaceBatchJson('result.written', 'tags::news'),
    'with a written item that is no object' => searchReplaceBatchJson('result.written.0', 'tags::news'),
    'with a written item of an unknown type' => searchReplaceBatchJson('result.written.0.type', 'user'),
    'with a written item without id' => searchReplaceBatchJson('result.written.0.id'),
    'with a written item without site' => searchReplaceBatchJson('result.written.0.site'),
    'with written fields that are no list' => searchReplaceBatchJson('result.written.0.fields', 'description'),
    'with a written field that is no object' => searchReplaceBatchJson('result.written.0.fields.0', 'description'),
    'with a written field without handle' => searchReplaceBatchJson('result.written.0.fields.0.handle'),
    'with a written field without before' => searchReplaceBatchJsonWithout('result.written.0.fields.0.before'),
    'with a written field without after' => searchReplaceBatchJsonWithout('result.written.0.fields.0.after'),
    'with a written field whose before is no text' => searchReplaceBatchJson('result.written.0.fields.0.before', 7),
    'with a written field whose after is no text' => searchReplaceBatchJson('result.written.0.fields.1.after', true),
    'with an undo that is no object' => searchReplaceBatchJson('undo', 'pending'),
    'with an undo whose user is no id' => searchReplaceBatchJson('undo.user', 7),
    'with an undo whose requested_at is no date' => searchReplaceBatchJson('undo.requested_at', 'gestern'),
    'with an undo result that is no object' => searchReplaceBatchJson('undo.result', 'undone'),
    'with an undo result whose undone_at is no date' => searchReplaceBatchJson('undo.result.undone_at', 'gestern'),
    'with an undo result whose restored is no number' => searchReplaceBatchJson('undo.result.restored', '0'),
    'with undo skipped items that are no list' => searchReplaceBatchJson('undo.result.skipped', 'tags::news'),
    'with an undo skipped item of an unknown reason' => searchReplaceBatchJson('undo.result.skipped.0.reason', 'gone'),
]);

it('lists the pending batches, the oldest first', function (): void {
    $batches = resolve(BatchRepository::class);
    $batches->save(pendingSearchReplaceBatch(1, '2026-11-19 10:05:00'));
    $batches->save(appliedSearchReplaceBatch(2, '2026-11-19 10:01:00'));
    $batches->save(pendingSearchReplaceBatch(3, '2026-11-19 10:00:00'));
    $batches->save(pendingSearchReplaceBatch(4, '2026-11-19 10:03:00'));
    Storage::disk('local')->put('juicebox/search-replace/'.searchReplaceBatchId(5).'.json', searchReplaceBatchJson('result').'!');

    expect($batches->pending()->all())->toEqual([
        pendingSearchReplaceBatch(3, '2026-11-19 10:00:00'),
        pendingSearchReplaceBatch(4, '2026-11-19 10:03:00'),
        pendingSearchReplaceBatch(1, '2026-11-19 10:05:00'),
    ]);
});

it('lists no pending batch before the first one', function (): void {
    expect(resolve(BatchRepository::class)->pending())->toBeEmpty();
});

it('lists the batches whose undo is pending, the oldest request first', function (): void {
    $batches = resolve(BatchRepository::class);
    $batches->save(undoingSearchReplaceBatch(1, '2026-11-19 10:00:00', '2026-11-19 11:05:00'));
    $batches->save(appliedSearchReplaceBatch(2, '2026-11-19 10:01:00'));
    $batches->save(undoingSearchReplaceBatch(3, '2026-11-19 10:02:00', '2026-11-19 11:00:00'));
    $batches->save(pendingSearchReplaceBatch(4, '2026-11-19 10:03:00'));
    $batches->save(undoingSearchReplaceBatch(5, '2026-11-19 10:04:00', '2026-11-19 11:01:00')
        ->undone(new UndoResult(CarbonImmutable::parse('2026-11-19 11:02:00'), 1, [])));
    $batches->save(undoingSearchReplaceBatch(6, '2026-11-19 10:05:00', '2026-11-19 11:03:00'));

    expect($batches->pendingUndos()->all())->toEqual([
        undoingSearchReplaceBatch(3, '2026-11-19 10:02:00', '2026-11-19 11:00:00'),
        undoingSearchReplaceBatch(6, '2026-11-19 10:05:00', '2026-11-19 11:03:00'),
        undoingSearchReplaceBatch(1, '2026-11-19 10:00:00', '2026-11-19 11:05:00'),
    ]);
});

it('lists the latest batches, the newest first', function (): void {
    $batches = resolve(BatchRepository::class);
    $batches->save(appliedSearchReplaceBatch(1, '2026-11-19 10:05:00'));
    $batches->save(pendingSearchReplaceBatch(2, '2026-11-19 10:07:00'));
    $batches->save(appliedSearchReplaceBatch(3, '2026-11-19 10:00:00'));

    expect($batches->latest(2)->all())->toEqual([
        pendingSearchReplaceBatch(2, '2026-11-19 10:07:00'),
        appliedSearchReplaceBatch(1, '2026-11-19 10:05:00'),
    ])->and($batches->latest(10))->toHaveCount(3);
});

it('deletes the batches done before the time, and keeps the pending ones and those whose undo is pending', function (): void {
    $batches = resolve(BatchRepository::class);
    $batches->save(pendingSearchReplaceBatch(1, '2026-11-01 10:00:00'));
    $batches->save(appliedSearchReplaceBatch(2));
    $batches->save(undoingSearchReplaceBatch(3, '2026-11-01 10:00:00', '2026-11-19 11:00:00'));
    $batches->save(undoingSearchReplaceBatch(4, '2026-11-01 10:00:00', '2026-11-19 11:00:00')
        ->undone(new UndoResult(CarbonImmutable::parse('2026-11-19 11:01:00'), 1, [])));
    $batches->save(undoingSearchReplaceBatch(5, '2026-11-01 10:00:00', '2026-11-19 11:00:00')
        ->undone(new UndoResult(CarbonImmutable::parse('2026-11-19 11:02:00'), 1, [])));

    expect($batches->prune(CarbonImmutable::parse('2026-11-19 11:02:00')))->toBe(2)
        ->and(Storage::disk('local')->allFiles())->toEqualCanonicalizing([
            'juicebox/search-replace/'.searchReplaceBatchId(1).'.json',
            'juicebox/search-replace/'.searchReplaceBatchId(3).'.json',
            'juicebox/search-replace/'.searchReplaceBatchId(5).'.json',
        ]);
});

it('deletes nothing when no batch is old enough', function (): void {
    $batches = resolve(BatchRepository::class);
    $batches->save(appliedSearchReplaceBatch());

    expect($batches->prune(CarbonImmutable::parse('2026-11-19 10:01:00')))->toBe(0)
        ->and($batches->latest(10))->toHaveCount(1);
});
