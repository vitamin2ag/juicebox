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

    /**
     * Exact redirects win over regex ones, and a lower order wins within each. Every URL is
     * tried with and without its query string. Regex sources work as in rias: case-insensitive,
     * not anchored, and only a destination with `$1` takes the captures.
     */
    public function findByUrl(string $site, string ...$urls): ?Redirect
    {
        $redirects = $this->whereSite($site)
            ->filter(fn (Redirect $redirect): bool => $redirect->enabled())
            ->sortBy(fn (Redirect $redirect): ?int => $redirect->order());

        return $this->findExact($redirects, $urls) ?? $this->findRegex($redirects, $urls);
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
     * @param  Collection<int, Redirect>  $redirects
     * @param  array<string>  $urls
     */
    private function findExact(Collection $redirects, array $urls): ?Redirect
    {
        $candidates = collect($urls)->flatMap(fn (string $url): array => [$this->normalise($url), $this->normalise(Str::before($url, '?'))]);

        return $redirects->first(fn (Redirect $redirect): bool => $redirect->matchType() === 'exact'
            && $redirect->source() !== null
            && $candidates->containsStrict($this->normalise($redirect->source())));
    }

    /**
     * @param  Collection<int, Redirect>  $redirects
     * @param  array<string>  $urls
     */
    private function findRegex(Collection $redirects, array $urls): ?Redirect
    {
        $urls = array_map($this->normalise(...), $urls);

        foreach ($redirects as $redirect) {
            if ($redirect->matchType() !== 'regex' || $redirect->source() === null) {
                continue;
            }

            $pattern = "`{$redirect->source()}`i";

            foreach ($urls as $url) {
                if (preg_match($pattern, $url) === 1) {
                    return $this->withCaptures($redirect, $pattern, $url);
                }
            }
        }

        return null;
    }

    private function withCaptures(Redirect $redirect, string $pattern, string $url): Redirect
    {
        $destination = $redirect->destination();

        if ($destination === null || ! str_contains($destination, '$')) {
            return $redirect;
        }

        return $redirect->withDestination(preg_replace($pattern, $destination, $url));
    }

    /**
     * Drops the slashes before the query string and at the end, and starts the URL with one.
     */
    private function normalise(string $url): string
    {
        return Str::of($url)->replaceMatches('#/+(?=\?|$)#', '')->start('/')->value();
    }
}
