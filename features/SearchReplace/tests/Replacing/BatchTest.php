<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Statamic\Auth\File\User;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Batch;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Patch;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Result;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\SkippedItem;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\SkipReason;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Undo;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\UndoResult;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\WrittenItem;

covers(Batch::class, Undo::class);

function teamBatch(): Batch
{
    return new Batch('9d3c1f2a-0b4e-4c6d-8e7f-000000000001', 'editor-1', 'Team', 'Praxis', true, CarbonImmutable::parse('2026-11-19 10:00:00'), [
        new Patch('entry', 'ueber-uns', 'de', 'title', 'Unser Team', 'Unser Praxis', 1),
    ]);
}

it('is pending until it has a result', function (): void {
    expect(teamBatch()->pending())->toBeTrue()
        ->and(teamBatch()->result)->toBeNull();
});

it('keeps everything but the result once it is applied', function (): void {
    $result = new Result(CarbonImmutable::parse('2026-11-19 10:01:00'), 0, 0, [new SkippedItem('entry', 'ueber-uns', 'de', SkipReason::Changed)]);

    $applied = teamBatch()->applied($result);

    expect($applied->pending())->toBeFalse()
        ->and($applied->result)->toBe($result)
        ->and($applied)->toEqual(new Batch(
            '9d3c1f2a-0b4e-4c6d-8e7f-000000000001',
            'editor-1',
            'Team',
            'Praxis',
            true,
            CarbonImmutable::parse('2026-11-19 10:00:00'),
            [new Patch('entry', 'ueber-uns', 'de', 'title', 'Unser Team', 'Unser Praxis', 1)],
            $result,
        ));
});

function writtenTeamBatch(): Batch
{
    return teamBatch()->applied(new Result(CarbonImmutable::parse('2026-11-19 10:01:00'), 1, 1, [], [
        new WrittenItem('entry', 'ueber-uns', 'de', [['handle' => 'title', 'before' => 'Unser Team', 'after' => 'Unser Praxis']]),
    ]));
}

function searchReplaceTeamUndo(): Undo
{
    return new Undo('admin-1', CarbonImmutable::parse('2026-11-19 11:00:00'));
}

/**
 * A control panel user with this id, who is a super user when asked to be.
 */
function searchReplaceBatchUser(string $id, bool $super = false): User
{
    $user = new User;
    $user->id($id);

    if ($super) {
        $user->makeSuper();
    }

    return $user;
}

it('may be undone once it is applied and wrote items, until an undo is asked for', function (Batch $batch, bool $undoable): void {
    expect($batch->undoable())->toBe($undoable);
})->with([
    'pending' => [teamBatch(), false],
    'applied without writing an item' => [fn (): Batch => teamBatch()->applied(new Result(CarbonImmutable::parse('2026-11-19 10:01:00'), 0, 0, [
        new SkippedItem('entry', 'ueber-uns', 'de', SkipReason::Changed),
    ])), false],
    'applied' => [writtenTeamBatch(), true],
    'asked to be undone' => [fn (): Batch => writtenTeamBatch()->withUndo(searchReplaceTeamUndo()), false],
    'undone' => [fn (): Batch => writtenTeamBatch()->withUndo(searchReplaceTeamUndo())->undone(new UndoResult(CarbonImmutable::parse('2026-11-19 11:01:00'), 1, [])), false],
]);

it('may be undone by :dataset', function (User $user, bool $allowed): void {
    expect(writtenTeamBatch()->mayBeUndoneBy($user))->toBe($allowed);
})->with([
    'the user who made it' => [fn (): User => searchReplaceBatchUser('editor-1'), true],
    'a super user' => [fn (): User => searchReplaceBatchUser('admin-1', super: true), true],
    'no other user' => [fn (): User => searchReplaceBatchUser('editor-2'), false],
]);

it('may be undone by no user but super users when it names no user', function (): void {
    $batch = new Batch('9d3c1f2a-0b4e-4c6d-8e7f-000000000001', null, 'Team', 'Praxis', false, CarbonImmutable::parse('2026-11-19 10:00:00'), []);

    expect($batch->mayBeUndoneBy(searchReplaceBatchUser('editor-1')))->toBeFalse();
});

it('keeps everything but the undo once an undo is asked for', function (): void {
    $batch = writtenTeamBatch()->withUndo(searchReplaceTeamUndo());

    expect($batch->undoPending())->toBeTrue()
        ->and($batch->undo)->toEqual(searchReplaceTeamUndo())
        ->and($batch)->toEqual(new Batch(
            '9d3c1f2a-0b4e-4c6d-8e7f-000000000001',
            'editor-1',
            'Team',
            'Praxis',
            true,
            CarbonImmutable::parse('2026-11-19 10:00:00'),
            [new Patch('entry', 'ueber-uns', 'de', 'title', 'Unser Team', 'Unser Praxis', 1)],
            writtenTeamBatch()->result,
            searchReplaceTeamUndo(),
        ));
});

it('keeps who asked for the undo and when once it is undone', function (): void {
    $result = new UndoResult(CarbonImmutable::parse('2026-11-19 11:01:00'), 0, [new SkippedItem('entry', 'ueber-uns', 'de', SkipReason::Changed)]);

    $batch = writtenTeamBatch()->withUndo(searchReplaceTeamUndo())->undone($result);

    expect($batch->undoPending())->toBeFalse()
        ->and($batch->undo)->toEqual(new Undo('admin-1', CarbonImmutable::parse('2026-11-19 11:00:00'), $result))
        ->and($batch->result)->toEqual(writtenTeamBatch()->result);
});

it('has no pending undo before one is asked for', function (): void {
    expect(writtenTeamBatch()->undoPending())->toBeFalse();
});

it('cannot be undone when nobody asked for the undo', function (): void {
    writtenTeamBatch()->undone(new UndoResult(CarbonImmutable::parse('2026-11-19 11:01:00'), 1, []));
})->throws(LogicException::class, 'Nobody asked for the undo of the batch.');

it('keeps its undo once it is applied', function (): void {
    $result = new Result(CarbonImmutable::parse('2026-11-19 10:01:00'), 1, 1, []);

    expect(teamBatch()->withUndo(searchReplaceTeamUndo())->applied($result)->undo)->toEqual(searchReplaceTeamUndo());
});

it('was done with when it was :dataset', function (Batch $batch, ?string $doneAt): void {
    expect($batch->doneAt()?->toIso8601String())->toBe($doneAt);
})->with([
    'pending' => [teamBatch(), null],
    'applied' => [writtenTeamBatch(), '2026-11-19T10:01:00+00:00'],
    'asked to be undone' => [writtenTeamBatch()->withUndo(searchReplaceTeamUndo()), null],
    'undone' => [
        writtenTeamBatch()
            ->withUndo(searchReplaceTeamUndo())
            ->undone(new UndoResult(CarbonImmutable::parse('2026-11-19 11:01:00'), 1, [])),
        '2026-11-19T11:01:00+00:00',
    ],
]);
