<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Shortlinks\Stache;

use Illuminate\Support\Collection;
use Vitamin2\Juicebox\Features\Shortlinks\Data\Shortlink;

class ShortlinkRepository
{
    public function __construct(private readonly ShortlinkStore $store) {}

    /**
     * @return Collection<int, Shortlink>
     */
    public function all(): Collection
    {
        return Collection::wrap($this->store->getItemsFromFiles())->whereInstanceOf(Shortlink::class)->values();
    }

    public function find(string $slug): ?Shortlink
    {
        return Collection::wrap($this->store->getItem($slug))->whereInstanceOf(Shortlink::class)->first();
    }

    public function save(Shortlink $shortlink): void
    {
        $this->store->save($shortlink);
    }

    public function delete(Shortlink $shortlink): void
    {
        $this->store->delete($shortlink);
    }
}
