<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Stache;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;

class RedirectRepository
{
    public function __construct(private readonly RedirectStore $store) {}

    /**
     * @return Collection<int, Redirect>
     */
    public function all(): Collection
    {
        return Collection::wrap($this->store->getItemsFromFiles())
            ->whereInstanceOf(Redirect::class)
            ->values();
    }

    public function find(string $id): ?Redirect
    {
        return Collection::wrap($this->store->getItem($id))
            ->whereInstanceOf(Redirect::class)
            ->first();
    }

    /**
     * Deletes the disabled redirects of every site and returns how many it deleted.
     */
    public function deleteDisabled(): int
    {
        $disabled = $this->all()->reject(fn (Redirect $redirect): bool => $redirect->enabled());
        $disabled->each($this->delete(...));

        return $disabled->count();
    }

    /**
     * Deletes the disabled redirects of every site and says how many it deleted.
     */
    public function prune(): string
    {
        return trans_choice(
            '{0} No disabled redirects to delete.|{1} Deleted :count disabled redirect.|[2,*] Deleted :count disabled redirects.',
            $this->deleteDisabled(),
        );
    }

    /**
     * The rows of the list for a site, with the fields of a redirect file, read from one index instead of every redirect.
     *
     * @return Collection<int, array<mixed>>
     */
    public function listing(string $site): Collection
    {
        return $this->store->index(RedirectStore::LISTING_INDEX)->items()
            ->filter(fn (mixed $row): bool => is_array($row) && ($row['site'] ?? null) === $site)
            ->map(fn (mixed $row): array => Collection::wrap($row)->except('site')->all())
            ->values();
    }

    /**
     * Exact redirects win over regex ones, and a lower order wins within each. Every URL is
     * tried with and without its query string. Regex sources work as in rias: case-insensitive,
     * not anchored, and only a destination with `$1` takes the captures.
     */
    public function findByUrl(string $site, string ...$urls): ?Redirect
    {
        return $this->findExact($site, $urls) ?? $this->findRegex($site, $urls);
    }

    /**
     * The order that puts a new redirect behind those of its site. Redirects without an order, which
     * come from rias, are tried before the numbered ones, so they stay in front of it.
     */
    public function nextOrder(string $site): int
    {
        $highest = $this->listing($site)
            ->pluck('order')
            ->filter(fn (mixed $order): bool => is_int($order))
            ->max();

        return is_int($highest) ? $highest + 1 : 1;
    }

    /**
     * The exact redirect of a site that redirects from this source, enabled or not.
     */
    public function findBySource(string $site, string $source): ?Redirect
    {
        $target = Redirect::normalise($source);

        $row = $this->listing($site)
            ->first(fn (array $row): bool => ($row['match_type'] ?? null) === 'exact'
                && is_string($row['source'] ?? null)
                && Redirect::normalise($row['source']) === $target);

        $id = $row['id'] ?? null;

        return is_string($id) ? $this->find($id) : null;
    }

    public function save(Redirect $redirect): void
    {
        $this->store->save($redirect);
    }

    public function delete(Redirect $redirect): void
    {
        $this->store->delete($redirect);
    }

    /**
     * @param  array<string>  $urls
     */
    private function findExact(string $site, array $urls): ?Redirect
    {
        $keys = collect($urls)->flatMap(fn (string $url): array => [
            Redirect::exactKey($site, $url),
            Redirect::exactKey($site, Str::before($url, '?')),
        ]);

        return $this->indexed(...$keys)->first();
    }

    /**
     * @param  array<string>  $urls
     */
    private function findRegex(string $site, array $urls): ?Redirect
    {
        $urls = collect($urls)->map(Redirect::normalise(...));

        return $this->indexed(Redirect::regexKey($site))
            ->lazy()
            ->map(fn (Redirect $redirect): ?Redirect => $this->matchRegex($redirect, $urls))
            ->first(fn (?Redirect $match): bool => $match instanceof Redirect);
    }

    /**
     * @param  Collection<int, string>  $urls
     */
    private function matchRegex(Redirect $redirect, Collection $urls): ?Redirect
    {
        $pattern = Redirect::rawPattern($redirect->source());

        if ($pattern === null) {
            return null;
        }

        $url = $urls->first(fn (string $url): bool => @preg_match($pattern, $url) === 1);

        return $url === null ? null : $this->withCaptures($redirect, $pattern, $url);
    }

    /**
     * The enabled redirects whose lookup key matches, lowest order first.
     *
     * @return Collection<int, Redirect>
     */
    private function indexed(string ...$keys): Collection
    {
        return $this->store->index(RedirectStore::LOOKUP_INDEX)->items()
            ->filter(fn (mixed $key): bool => in_array($key, $keys, true))
            ->keys()
            ->map(fn (mixed $id): ?Redirect => is_string($id) ? $this->find($id) : null)
            ->filter()
            ->sortBy(fn (Redirect $redirect): ?int => $redirect->order())
            ->values();
    }

    private function withCaptures(Redirect $redirect, string $pattern, string $url): Redirect
    {
        $destination = $redirect->destination();

        if ($destination === null || ! str_contains($destination, '$')) {
            return $redirect;
        }

        return $redirect->withDestination(preg_replace($pattern, $destination, $url));
    }
}
