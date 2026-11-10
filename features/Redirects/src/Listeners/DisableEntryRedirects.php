<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Listeners;

use Statamic\Events\EntryDeleted;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;

/**
 * A redirect to a deleted entry leads nowhere, so it is disabled rather than deleted: the list still
 * shows it, it can be pointed somewhere else, and the prune command removes it.
 */
class DisableEntryRedirects
{
    public function __construct(private readonly RedirectRepository $redirects) {}

    public function handle(EntryDeleted $event): void
    {
        if (! config('juicebox.redirects.enabled')) {
            return;
        }

        $this->redirects->withDestination(Redirect::toEntry($event->entry))
            ->each(fn (Redirect $redirect) => $this->redirects->save($redirect->withEnabled(false)));
    }
}
