<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Http\Controllers;

use Illuminate\Support\Collection;
use Override;
use Statamic\Http\Controllers\CP\ActionController;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;

class RedirectActionController extends ActionController
{
    /**
     * @return Collection<int, Redirect>
     */
    #[Override]
    protected function getSelectedItems(mixed $items, mixed $context): Collection
    {
        $repository = resolve(RedirectRepository::class);

        $redirects = Collection::wrap($items)
            ->map(fn (mixed $id): mixed => is_string($id) ? $repository->find($id) : null)
            ->whereInstanceOf(Redirect::class)
            ->values();

        // Statamic offers every action for a selection without items, so there is nothing to answer.
        abort_if($redirects->isEmpty(), 404);

        return $redirects;
    }
}
