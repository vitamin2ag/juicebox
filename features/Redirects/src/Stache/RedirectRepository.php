<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Stache;

use Illuminate\Support\Collection;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;

class RedirectRepository
{
    public function __construct(private readonly RedirectStore $store) {}

    /**
     * @return Collection<int, Redirect>
     */
    public function all(): Collection
    {
        return Collection::wrap($this->store->getItemsFromFiles())->whereInstanceOf(Redirect::class)->values();
    }

    public function find(string $id): ?Redirect
    {
        return Collection::wrap($this->store->getItem($id))->whereInstanceOf(Redirect::class)->first();
    }

    /**
     * @return Collection<int, Redirect>
     */
    public function whereSite(string $site): Collection
    {
        return $this->all()->filter(fn (Redirect $redirect): bool => $redirect->site() === $site)->values();
    }

    /**
     * @return Collection<int, Redirect>
     */
    public function whereEnabled(bool $enabled): Collection
    {
        return $this->all()->filter(fn (Redirect $redirect): bool => $redirect->enabled() === $enabled)->values();
    }

    public function save(Redirect $redirect): void
    {
        $this->store->save($redirect);
    }

    public function delete(Redirect $redirect): void
    {
        $this->store->delete($redirect);
    }
}
