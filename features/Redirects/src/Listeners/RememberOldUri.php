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
     * @return Collection<int, string>
     */
    public static function movedIds(CollectionTree $tree): Collection
    {
        return Collection::wrap($tree->diff()->ancestryChanged());
    }

    public function handle(EntrySaving|CollectionTreeSaving $event): void
    {
        if (! config('juicebox.redirects.enabled')) {
            return;
        }

        $ids = $event instanceof EntrySaving
            ? Collection::wrap($event->entry->id())
            : self::movedIds($event->tree);

        $ids->each($this->remember(...));
    }

    private function remember(mixed $id): void
    {
        $entry = Entries::find($id);

        if ($entry?->published()) {
            Cache::put(self::key($entry), $entry->uri(), now()->addMinute());
        }
    }
}
