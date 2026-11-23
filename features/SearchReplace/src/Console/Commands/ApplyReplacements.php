<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Batch;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\BatchRepository;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Replacer;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Result;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\UndoResult;

#[Signature('statamic:juicebox:search-replace:apply')]
#[Description('Apply the pending search and replace batches and undos to the content')]
class ApplyReplacements extends Command
{
    use RunsInPlease;

    /**
     * Each batch is saved as applied, or undone, right after it is, so a run that stops halfway does not do it again.
     * The pending batches come first, so an undo sees what they changed.
     */
    public function handle(BatchRepository $batches, Replacer $replacer): int
    {
        if ($this->disabled()) {
            return self::FAILURE;
        }

        $results = $batches->pending()->map(function (Batch $batch) use ($batches, $replacer): Result {
            $result = $replacer->apply($batch);
            $batches->save($batch->applied($result));

            return $result;
        });

        $undos = $batches->pendingUndos()->map(function (Batch $batch) use ($batches, $replacer): UndoResult {
            $result = $replacer->undo($batch);
            $batches->save($batch->undone($result));

            return $result;
        });

        $this->info(trans_choice('Applied :count batch|Applied :count batches', $results->count()).', '
            .trans_choice('replaced :count match|replaced :count matches', $results->sum(fn (Result $result): int => $result->replaced)).', '
            .trans_choice('skipped :count item|skipped :count items', $results->sum(fn (Result $result): int => count($result->skipped))).'.');
        $this->info(trans_choice('Undid :count batch|Undid :count batches', $undos->count()).', '
            .trans_choice('restored :count item|restored :count items', $undos->sum(fn (UndoResult $result): int => $result->restored)).', '
            .trans_choice('skipped :count item|skipped :count items', $undos->sum(fn (UndoResult $result): int => count($result->skipped))).'.');

        return self::SUCCESS;
    }

    /**
     * Prints the error and returns true while the feature is disabled.
     */
    private function disabled(): bool
    {
        if (config('juicebox.search_replace.enabled')) {
            return false;
        }

        $this->error(__('Search & Replace is disabled: juicebox.search_replace.enabled is false.'));

        return true;
    }
}
