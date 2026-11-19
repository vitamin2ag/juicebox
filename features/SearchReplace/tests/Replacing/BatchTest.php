<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Batch;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Patch;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Result;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\SkippedItem;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\SkipReason;

covers(Batch::class);

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
