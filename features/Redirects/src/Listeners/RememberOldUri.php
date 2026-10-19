<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Listeners;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Statamic\Entries\Entry;
use Statamic\Events\CollectionTreeSaving;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Entry as Entries;
use Statamic\Structures\CollectionTree;
use Statamic\Structures\CollectionTreeDiff;
use UnexpectedValueException;

/**
 * Saving runs while the Stache still holds the old URI; CreateRedirect reads it back once the entry or tree is saved.
 */
class RememberOldUri
{
    public static function key(Entry $entry): string
    {
        $id = $entry->id();

        return is_string($id) ? "juicebox-redirects-old-uri:{$id}" : throw new UnexpectedValueException('The entry has no id.');
    }

    /**
     * Only a new parent changes the URI of an entry and its children; an entry the tree just
     * added was never at its old URI for long, so it gets no redirect.
     *
     * @return Collection<array-key, mixed>
     */
    public static function movedIds(mixed $tree): Collection
    {
        $tree = Collection::wrap($tree)->whereInstanceOf(CollectionTree::class)->firstOrFail();

        return Collection::wrap(Collection::wrap($tree->diff())->whereInstanceOf(CollectionTreeDiff::class)->firstOrFail()->ancestryChanged());
    }

    public function handle(EntrySaving|CollectionTreeSaving $event): void
    {
        if (! config('juicebox.redirects.enabled') || ! config('juicebox.redirects.create_entry_redirects')) {
            return;
        }

        $ids = $event instanceof EntrySaving
            ? Collection::wrap($event->entry)->whereInstanceOf(Entry::class)->map(fn (Entry $entry): mixed => $entry->id())
            : self::movedIds($event->tree);

        $ids->each($this->remember(...));
    }

    private function remember(mixed $id): void
    {
        $entry = Collection::wrap(Entries::find($id))->whereInstanceOf(Entry::class)->first();

        if ($entry?->published()) {
            Cache::put(self::key($entry), $entry->uri(), now()->addMinute());
        }
    }
}
