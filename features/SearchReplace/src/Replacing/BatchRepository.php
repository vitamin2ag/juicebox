<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Replacing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Keeps every batch as a JSON file of its own on the local disk, outside the content and the cache, so the control
 * panel and the command never write the same file, until it is pruned. A file that cannot be read or holds no batch
 * counts as missing.
 */
class BatchRepository
{
    public function save(Batch $batch): void
    {
        Storage::disk('local')->put(
            $this->path($batch->id),
            json_encode($batch->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * Only a UUID names a batch, so an id cannot point to a file elsewhere.
     */
    public function find(string $id): ?Batch
    {
        return Str::isUuid($id) ? $this->read($this->path($id)) : null;
    }

    /**
     * @return Collection<int, Batch> the oldest first
     */
    public function pending(): Collection
    {
        return $this->all()
            ->filter(fn (Batch $batch): bool => $batch->pending())
            ->values();
    }

    /**
     * @return Collection<int, Batch> the oldest request first
     */
    public function pendingUndos(): Collection
    {
        return $this->all()
            ->filter(fn (Batch $batch): bool => $batch->undoPending())
            ->sortBy(fn (Batch $batch): ?CarbonImmutable => $batch->undo->requestedAt ?? null)
            ->values();
    }

    /**
     * @return Collection<int, Batch> the newest first
     */
    public function latest(int $limit): Collection
    {
        return $this->all()
            ->reverse()
            ->take($limit)
            ->values();
    }

    /**
     * Deletes the batches the command was done with before the time, applied ones and undone ones, and returns how many.
     * A pending batch, or one whose undo is pending, is kept, however old.
     */
    public function prune(CarbonImmutable $before): int
    {
        $done = $this->all()
            ->filter(fn (Batch $batch): bool => $batch->doneAt()?->isBefore($before) ?? false);
        $paths = $done->map(fn (Batch $batch): string => $this->path($batch->id))->all();

        Storage::disk('local')->delete($paths);

        return $done->count();
    }

    /**
     * @return Collection<int, Batch> the oldest first
     */
    private function all(): Collection
    {
        return collect(Storage::disk('local')->files('juicebox/search-replace'))
            ->map($this->read(...))
            ->whereInstanceOf(Batch::class)
            ->sortBy(fn (Batch $batch): int => $batch->createdAt->getTimestamp())
            ->values();
    }

    private function read(string $path): ?Batch
    {
        try {
            return Batch::fromArray(json_decode(Storage::disk('local')->get($path) ?? 'null', true));
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function path(string $id): string
    {
        return "juicebox/search-replace/{$id}.json";
    }
}
