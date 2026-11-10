<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Statamic\Facades\Site as Sites;
use Statamic\Facades\URL;
use Statamic\Sites\Site;
use Symfony\Component\HttpFoundation\Response;
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

        if ($response->getStatusCode() !== 404) {
            return $response;
        }

        $url = $request->getSchemeAndHttpHost().$request->getRequestUri();
        $site = Sites::findByUrl($url) ?? Sites::default();

        // On a site with an absolute URL a source may carry the site prefix, so the full path is tried as well.
        // The request is percent-encoded, while a source is typed or imported as it reads.
        $redirect = $this->redirects->findByUrl(
            $site->handle(),
            $site->relativePath($url),
            $request->getRequestUri(),
            rawurldecode($site->relativePath($url)),
            rawurldecode($request->getRequestUri()),
        );

        if (! $redirect instanceof Redirect) {
            return $response;
        }

        if ($redirect->type() === 410) {
            abort(410);
        }

        $destination = $this->resolve($redirect->destination());

        if ($destination === null) {
            return $response;
        }

        return redirect($this->withSitePrefix($site, $destination), $redirect->type());
    }

    private function resolve(?string $destination): ?string
    {
        if ($destination === null || ! str_starts_with($destination, 'entry::')) {
            return $destination;
        }

        return Redirect::entryOf($destination)?->absoluteUrl();
    }

    /**
     * A destination gets the prefix of its site unless it starts with the prefix of a site.
     * A destination with a scheme or without one, like `mailto:` or `//host`, leads away from the sites and stays as it is.
     */
    private function withSitePrefix(Site $site, string $destination): string
    {
        if ($this->leavesTheSites($destination)) {
            return $destination;
        }

        $prefixed = Sites::all()
            ->map($this->prefix(...))
            ->filter()
            ->contains(fn (string $prefix): bool => str_starts_with($destination.'/', $prefix.'/'));

        if ($prefixed) {
            return $destination;
        }

        return $this->prefix($site).'/'.ltrim($destination, '/');
    }

    private function leavesTheSites(string $destination): bool
    {
        return preg_match('#^(//|[a-z][a-z0-9+.-]*:)#i', $destination) === 1;
    }

    private function prefix(Site $site): string
    {
        return rtrim(URL::makeRelative($site->url()), '/');
    }
}
