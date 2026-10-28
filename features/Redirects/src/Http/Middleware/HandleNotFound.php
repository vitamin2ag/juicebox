<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\Site as Sites;
use Statamic\Facades\URL;
use Statamic\Sites\Site;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;

class HandleNotFound
{
    public function __construct(private readonly RedirectRepository $redirects) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 404 || ! config('juicebox.redirects.enabled')) {
            return $response;
        }

        $url = $request->getSchemeAndHttpHost().$request->getRequestUri();
        $site = Collection::wrap(Sites::findByUrl($url) ?? Sites::default())->whereInstanceOf(Site::class)->firstOrFail();

        // rias compared the full path on sites with an absolute URL, so their sources may carry the site prefix.
        $redirect = $this->redirects->findByUrl($this->string($site->handle()), $this->string($site->relativePath($url)), $request->getRequestUri());

        if (! $redirect instanceof Redirect) {
            return $response;
        }

        if ($redirect->type() === 410) {
            abort(410);
        }

        $destination = $this->resolve($redirect->destination());

        return $destination === null ? $response : redirect($this->withSitePrefix($site, $destination), $redirect->type());
    }

    private function resolve(?string $destination): ?string
    {
        if ($destination === null || ! str_starts_with($destination, 'entry::')) {
            return $destination;
        }

        $url = Collection::wrap(Entries::find(Str::after($destination, 'entry::')))->whereInstanceOf(Entry::class)->first()?->absoluteUrl();

        return is_string($url) ? $url : null;
    }

    /**
     * As in rias, a destination gets the prefix of its site unless it starts with the prefix of a site.
     */
    private function withSitePrefix(Site $site, string $destination): string
    {
        $prefixes = Collection::wrap(Sites::all())->whereInstanceOf(Site::class)->map($this->prefix(...))->filter();

        if (URL::isAbsolute($destination) || $prefixes->contains(fn (string $prefix): bool => str_starts_with($destination.'/', $prefix.'/'))) {
            return $destination;
        }

        return $this->prefix($site).'/'.ltrim($destination, '/');
    }

    private function prefix(Site $site): string
    {
        return rtrim(URL::makeRelative($this->string($site->url())), '/');
    }

    /**
     * Statamic's site methods return strings but declare no type.
     */
    private function string(mixed $value): string
    {
        return is_string($value) ? $value : throw new UnexpectedValueException('Statamic returned no string.');
    }
}
