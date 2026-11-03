<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Listeners;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Statamic\Entries\Entry;
use Statamic\Events\CollectionTreeSaved;
use Statamic\Events\EntrySaved;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry as Entries;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;

class CreateRedirect
{
    public function __construct(private readonly RedirectRepository $redirects) {}

    public function handle(EntrySaved|CollectionTreeSaved $event): void
    {
        if (! config('juicebox.redirects.enabled')) {
            return;
        }

        $entries = $event instanceof EntrySaved
            ? $this->descendants($event->entry)->prepend($event->entry)
            : RememberOldUri::movedIds($event->tree)
                ->map(fn (mixed $id): ?Entry => Entries::find($id));

        $entries->filter()->each($this->redirect(...));
    }

    /**
     * The entries below one whose slug changed, if RememberOldUri marked it. Statamic has updated their uris by now.
     *
     * @return Collection<int, Entry>
     */
    private function descendants(Entry $entry): Collection
    {
        if (! Blink::pull(RememberOldUri::descendantsKey($entry))) {
            return new Collection;
        }

        return RememberOldUri::descendantIds($entry)
            ->map(fn (string $id): ?Entry => Entries::find($id))
            ->filter()
            ->values();
    }

    private function redirect(Entry $entry): void
    {
        $uri = $entry->uri();
        $oldUri = Blink::pull(RememberOldUri::key($entry));

        if (! is_string($uri) || $oldUri === $uri) {
            return;
        }

        $site = $entry->locale();

        if ($entry->published()) {
            $this->disableConflict($site, $uri);
        }

        if (is_string($oldUri) && config('juicebox.redirects.create_entry_redirects')) {
            $this->redirectFrom($site, $oldUri, $uri);
        }
    }

    /**
     * A redirect from the old uri that is already stored is not added twice: a disabled one is reused,
     * which keeps its id, order and description, and an enabled one is a deliberate redirect and stays.
     */
    private function redirectFrom(string $site, string $oldUri, string $uri): void
    {
        $stored = $this->redirects->findBySource($site, $oldUri);

        if (! $stored instanceof Redirect) {
            $this->redirects->save(new Redirect(
                id: Str::uuid()->toString(),
                site: $site,
                source: $oldUri,
                destination: $uri,
                order: $this->redirects->nextOrder($site),
            ));
        } elseif (! $stored->enabled()) {
            $this->redirects->save($stored->withValues(
                $stored->source(),
                $uri,
                301,
                $stored->matchType(),
                true,
                $stored->description(),
            ));
        }
    }

    /**
     * A regex redirect only runs on a 404, so it never conflicts with an entry: only the exact redirect
     * from the uri is disabled.
     */
    private function disableConflict(string $site, string $uri): void
    {
        $stored = $this->redirects->findBySource($site, $uri);

        if ($stored instanceof Redirect && $stored->enabled()) {
            $this->redirects->save($stored->withEnabled(false));
        }
    }
}
