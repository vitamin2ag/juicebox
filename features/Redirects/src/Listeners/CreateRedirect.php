<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Listeners;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Statamic\Entries\Entry;
use Statamic\Events\CollectionTreeSaved;
use Statamic\Events\EntrySaved;
use Statamic\Facades\Entry as Entries;
use UnexpectedValueException;
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
            ? Collection::wrap($event->entry)
            : RememberOldUri::movedIds($event->tree)->map(fn (mixed $id): mixed => Entries::find($id));

        $entries->whereInstanceOf(Entry::class)->each($this->redirect(...));
    }

    private function redirect(Entry $entry): void
    {
        $uri = $entry->uri();
        $oldUri = Cache::pull(RememberOldUri::key($entry));

        if (! is_string($uri) || $oldUri === $uri) {
            return;
        }

        $site = $this->string($entry->locale());

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
            $this->redirects->save(new Redirect(id: Str::uuid()->toString(), site: $site, source: $oldUri, destination: $uri, order: $this->redirects->nextOrder($site)));
        } elseif (! $stored->enabled()) {
            $this->redirects->save($stored->withValues($stored->source(), $uri, 301, $stored->matchType(), true, $stored->description()));
        }
    }

    /**
     * The redirect findByUrl() returns has the regex captures in its destination, so the stored one is disabled.
     */
    private function disableConflict(string $site, string $uri): void
    {
        $id = $this->redirects->findByUrl($site, $uri)?->id();
        $stored = $id === null ? null : $this->redirects->find($id);

        if ($stored instanceof Redirect) {
            $this->redirects->save($stored->withEnabled(false));
        }
    }

    /**
     * Statamic's entry methods return strings but declare no type.
     */
    private function string(mixed $value): string
    {
        return is_string($value) ? $value : throw new UnexpectedValueException('Statamic returned no string.');
    }
}
