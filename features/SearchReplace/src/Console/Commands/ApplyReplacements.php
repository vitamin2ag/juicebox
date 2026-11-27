<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
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
     * Runs only while no other run, scheduled or by hand, holds the lock, which would apply the same batches again. A
     * run that dies keeps the lock, so it expires after ten minutes, far longer than a run takes.
     */
    public function handle(BatchRepository $batches, Replacer $replacer): int
    {
        if ($this->disabled()) {
            return self::FAILURE;
        }

        $lock = Cache::lock('juicebox:search-replace:apply', 600);

        if (! $lock->get()) {
            $this->info(__('Another run is still applying the batches.'));

            return self::SUCCESS;
        }

        try {
            $this->apply($batches, $replacer);
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }

    /**
     * Each batch is saved as applied, or undone, right after it is, so a run that stops halfway does not do it again.
     * The pending batches come first, so an undo sees what they changed, and the old batches are pruned last.
     */
    private function apply(BatchRepository $batches, Replacer $replacer): void
    {
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
        $this->prune($batches);
    }

    /**
     * Deletes the batches done more than keep_days days ago. A keep_days that is no whole number above 0, such as false,
     * keeps every batch.
     */
    private function prune(BatchRepository $batches): void
    {
        $days = filter_var(config('juicebox.search_replace.keep_days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($days === false) {
            return;
        }

        $deleted = $batches->prune(CarbonImmutable::now()->subDays($days));

        $this->info(trans_choice('Deleted :count old batch.|Deleted :count old batches.', $deleted));
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
