<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Statamic\Auth\File\User;
use Vitamin2\Juicebox\Features\SearchReplace\Http\Controllers\UndoController;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Batch;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\BatchRepository;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Result;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\SkippedItem;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\SkipReason;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Undo;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\UndoResult;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\WrittenItem;
use Vitamin2\Juicebox\Features\SearchReplace\ServiceProvider;
use Vitamin2\Juicebox\Features\SearchReplace\Tests\__Fixtures__\SearchReplaceFixtures;

covers(UndoController::class, ServiceProvider::class);

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
    $this->travelTo(CarbonImmutable::parse('2026-11-19 11:00:00'));
});

function searchReplaceUndoBatchId(): string
{
    return '9d3c1f2a-0b4e-4c6d-8e7f-000000000001';
}

/**
 * A batch the user with this id made, which the command applied and which wrote one entry.
 */
function searchReplaceUndoableBatch(?string $user = 'editor-1'): Batch
{
    return new Batch(searchReplaceUndoBatchId(), $user, 'Team', 'Praxis', false, CarbonImmutable::parse('2026-11-19 10:00:00'), [], new Result(
        CarbonImmutable::parse('2026-11-19 10:01:00'),
        1,
        1,
        [],
        [new WrittenItem('entry', 'ueber-uns', 'de', [['handle' => 'title', 'before' => 'Unser Team', 'after' => 'Unser Praxis']])],
    ));
}

/**
 * Saves a control panel user with this id who may use the utility, or a super user.
 */
function searchReplaceUndoUser(string $id, bool $super = false): User
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
 * @param  array<mixed>  $data
 * @return TestResponse<Illuminate\Http\Response>
 */
function searchReplaceUndoPost(User $user, array $data = ['batch' => '9d3c1f2a-0b4e-4c6d-8e7f-000000000001']): TestResponse
{
    return test()
        ->actingAs($user)
        ->post(SearchReplaceFixtures::cpUrl('utilities.search-replace.undo'), $data);
}

function searchReplaceUndoSaved(): ?Batch
{
    return resolve(BatchRepository::class)->find(searchReplaceUndoBatchId());
}

it('asks for the undo of a batch :dataset and goes back to the utility', function (User $user, string $id): void {
    resolve(BatchRepository::class)->save(searchReplaceUndoableBatch());

    searchReplaceUndoPost($user)
        ->assertRedirect(SearchReplaceFixtures::cpUrl('utilities.search-replace'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'The batch will be undone at the next scheduled run.');

    expect(searchReplaceUndoSaved())->toEqual(searchReplaceUndoableBatch()->withUndo(new Undo($id, CarbonImmutable::parse('2026-11-19 11:00:00'))));
})->with([
    'that the user made' => [fn (): User => searchReplaceUndoUser('editor-1'), 'editor-1'],
    'that another user made, as a super user' => [fn (): User => searchReplaceUndoUser('admin-1', super: true), 'admin-1'],
]);

it('forbids the undo of a batch :dataset', function (Batch $batch): void {
    resolve(BatchRepository::class)->save($batch);

    searchReplaceUndoPost(searchReplaceUndoUser('editor-2'))->assertForbidden();

    expect(searchReplaceUndoSaved())->toEqual($batch);
})->with([
    'that another user made' => fn (): Batch => searchReplaceUndoableBatch(),
    'that names no user' => fn (): Batch => searchReplaceUndoableBatch(null),
    'that cannot be undone, which another user made' => fn (): Batch => searchReplaceUndoableBatch()->withUndo(new Undo('editor-1', CarbonImmutable::parse('2026-11-19 10:30:00'))),
]);

it('refuses the undo of a batch that :dataset', function (Batch $batch): void {
    resolve(BatchRepository::class)->save($batch);

    searchReplaceUndoPost(searchReplaceUndoUser('editor-1'))
        ->assertSessionHasErrors(['batch' => 'This batch cannot be undone.']);

    expect(searchReplaceUndoSaved())->toEqual($batch);
})->with([
    'is pending' => fn (): Batch => new Batch(searchReplaceUndoBatchId(), 'editor-1', 'Team', 'Praxis', false, CarbonImmutable::parse('2026-11-19 10:00:00'), []),
    'wrote no item' => fn (): Batch => new Batch(searchReplaceUndoBatchId(), 'editor-1', 'Team', 'Praxis', false, CarbonImmutable::parse('2026-11-19 10:00:00'), [], new Result(
        CarbonImmutable::parse('2026-11-19 10:01:00'),
        0,
        0,
        [new SkippedItem('entry', 'ueber-uns', 'de', SkipReason::Changed)],
    )),
    'is asked to be undone already' => fn (): Batch => searchReplaceUndoableBatch()->withUndo(new Undo('admin-1', CarbonImmutable::parse('2026-11-19 10:30:00'))),
    'is undone' => fn (): Batch => searchReplaceUndoableBatch()
        ->withUndo(new Undo('editor-1', CarbonImmutable::parse('2026-11-19 10:30:00')))
        ->undone(new UndoResult(CarbonImmutable::parse('2026-11-19 10:31:00'), 1, [])),
]);

it('refuses the undo of :dataset', function (string $id): void {
    resolve(BatchRepository::class)->save(searchReplaceUndoableBatch());

    searchReplaceUndoPost(searchReplaceUndoUser('editor-1'), ['batch' => $id])
        ->assertSessionHasErrors(['batch' => 'This batch cannot be undone.']);

    expect(searchReplaceUndoSaved())->toEqual(searchReplaceUndoableBatch());
})->with([
    'a batch that was never saved' => '9d3c1f2a-0b4e-4c6d-8e7f-000000000002',
    'an id that is no UUID' => 'team',
]);

/*
 * Statamic's messages of the control panel, which tell each rule apart from the error of a batch that cannot be undone.
 */
it('rejects :dataset', function (array $data, string $message): void {
    resolve(BatchRepository::class)->save(searchReplaceUndoableBatch());

    searchReplaceUndoPost(searchReplaceUndoUser('editor-1'), $data)->assertSessionHasErrors(['batch' => $message]);

    expect(searchReplaceUndoSaved())->toEqual(searchReplaceUndoableBatch());
})->with([
    'no batch' => [[], 'This field is required.'],
    'a list as batch' => [['batch' => [searchReplaceUndoBatchId()]], 'Must be a string.'],
]);

it('forbids the undo without the access search_replace utility permission', function (): void {
    resolve(BatchRepository::class)->save(searchReplaceUndoableBatch());
    $user = SearchReplaceFixtures::userWith('access de site');
    $user->id('editor-1');
    $user->save();

    $this->actingAs($user)
        ->postJson(SearchReplaceFixtures::cpUrl('utilities.search-replace.undo'), ['batch' => searchReplaceUndoBatchId()])
        ->assertForbidden();

    expect(searchReplaceUndoSaved())->toEqual(searchReplaceUndoableBatch());
});
