<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\DeadLinks\Checking;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Statamic\Entries\Entry;
use Statamic\Facades\Asset;
use Statamic\Facades\Data;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\Site;
use Statamic\Facades\URL;
use Statamic\Structures\Page;

/**
 * Checks each link once. References to entries and assets are looked up in Statamic, and so are URLs of the sites;
 * every other URL, and a URL of a site that Statamic does not know (a Laravel route, a file, a redirect), is requested.
 */
class LinkChecker
{
    /**
     * The result of every link, keyed by its URL. A URL that is a number, such as `2024`, is an integer key.
     *
     * @param  iterable<array-key, string>  $urls
     * @return Collection<string, CheckResult>
     */
    public function check(iterable $urls): Collection
    {
        $targets = collect($urls)
            ->mapWithKeys(fn (string $url): array => [$url => $this->absolute($url)]);
        $results = $targets->map($this->resolve(...))->whereInstanceOf(CheckResult::class);

        return $results->replace($this->request($targets->except($results->keys())));
    }

    /**
     * A path is requested on the domain of the default site.
     */
    private function absolute(string $url): string
    {
        if (! str_starts_with($url, '/')) {
            return $url;
        }

        return Str::match('~^https?://[^/]+~i', URL::makeAbsolute(Site::default()->url())).$url;
    }

    /**
     * The result of a link that Statamic can answer, or null for a URL that has to be requested.
     */
    private function resolve(string $target, string $url): ?CheckResult
    {
        if (str_starts_with($url, 'entry::')) {
            $entry = Entries::find(Str::match('~^entry::([^?#]*)~', $url));

            return $entry === null
                ? new CheckResult($url, Reason::MissingEntry)
                : $this->published($url, $entry);
        }

        if (str_starts_with($url, 'asset::')) {
            return Asset::find(Str::after($url, 'asset::')) === null
                ? new CheckResult($url, Reason::MissingAsset)
                : new CheckResult($url);
        }

        if (! $this->isWebUrl($url)) {
            return new CheckResult($url, Reason::InvalidUrl);
        }

        $item = Data::findByRequestUrl($target);
        $entry = $item instanceof Page ? $item->entry() : $item;

        if ($entry instanceof Entry) {
            return $this->published($url, $entry);
        }

        return $item === null ? null : new CheckResult($url);
    }

    /**
     * Statamic shows a draft, or a dated entry that is private for now, as a 404.
     */
    private function published(string $url, Entry $entry): CheckResult
    {
        return $entry->status() === 'published'
            ? new CheckResult($url)
            : new CheckResult($url, Reason::UnpublishedEntry);
    }

    /**
     * A path on the site, or an http(s) URL whose host the HTTP client accepts. A mistyped scheme (`https::/…`),
     * a missing one (`www.example.com`), a space in the host or a port out of range make the URL invalid.
     */
    private function isWebUrl(string $url): bool
    {
        return preg_match('~^/(?!/)~', $url) === 1
            || (preg_match('~^https?://[^\x00-\x20\x7F/?#\\\\]+([/?#]|$)~i', $url) === 1 && parse_url($url) !== false);
    }

    /**
     * HEAD is enough for most servers; a URL whose HEAD gets an error is asked again with GET, which some servers
     * answer differently.
     *
     * @param  Collection<string, string>  $targets
     * @return Collection<string, CheckResult>
     */
    private function request(Collection $targets): Collection
    {
        $heads = $this->send('HEAD', $targets);
        $gets = $this->send('GET', $targets->filter(
            fn (string $target, string $url): bool => $this->failed($heads[$url]),
        ));

        return $targets->map(
            fn (string $target, string $url): CheckResult => $this->result($url, $gets[$url] ?? $heads[$url]),
        );
    }

    /**
     * The response of each URL, or the exception of a connection that failed.
     *
     * @param  Collection<string, string>  $targets
     * @return array<array-key, mixed>
     */
    private function send(string $method, Collection $targets): array
    {
        $timeout = config()->integer('juicebox.dead_links.timeout');

        return Http::pool(function (Pool $pool) use ($method, $targets, $timeout): void {
            foreach ($targets as $url => $target) {
                $pool->as($url)
                    ->timeout($timeout)
                    ->withUserAgent('Mozilla/5.0 (compatible; Juicebox Dead Links; +https://vitamin2.ch)')
                    ->send($method, $target);
            }
        }, 10);
    }

    private function failed(mixed $response): bool
    {
        return $response instanceof Response && $response->failed();
    }

    private function result(string $url, mixed $response): CheckResult
    {
        if (! $response instanceof Response) {
            return new CheckResult($url, Reason::ConnectionFailed);
        }

        return new CheckResult($url, $this->reason($response), $response->status());
    }

    /**
     * 401, 403 and 429 are not reported: bot protection (Cloudflare, LinkedIn) answers them to pages that work.
     */
    private function reason(Response $response): ?Reason
    {
        return match ($response->status()) {
            401, 403, 429 => null,
            404, 410 => Reason::PageNotFound,
            default => $response->failed() ? Reason::HttpError : null,
        };
    }
}
