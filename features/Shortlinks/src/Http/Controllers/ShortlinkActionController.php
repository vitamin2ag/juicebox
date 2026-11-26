<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Shortlinks\Http\Controllers;

use Illuminate\Support\Collection;
use Override;
use Statamic\Http\Controllers\CP\ActionController;
use Vitamin2\Juicebox\Features\Shortlinks\Data\Shortlink;
use Vitamin2\Juicebox\Features\Shortlinks\Stache\ShortlinkRepository;

class ShortlinkActionController extends ActionController
{
    /**
     * @return Collection<int, Shortlink>
     */
    #[Override]
    protected function getSelectedItems(mixed $items, mixed $context): Collection
    {
        $repository = resolve(ShortlinkRepository::class);

        $shortlinks = Collection::wrap($items)
            ->map(fn (mixed $slug): mixed => is_string($slug) ? $repository->find($slug) : null)
            ->whereInstanceOf(Shortlink::class)
            ->values();

        // Statamic offers every action for a selection without items, so there is nothing to answer.
        abort_if($shortlinks->isEmpty(), 404);

        return $shortlinks;
    }
}
