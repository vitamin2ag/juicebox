<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Shortlinks\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Uri;
use Statamic\Facades\Site as Sites;
use Vitamin2\Juicebox\Features\Shortlinks\Data\Shortlink;
use Vitamin2\Juicebox\Features\Shortlinks\Stache\ShortlinkRepository;

class ShortlinkRedirectController
{
    public function __construct(private readonly ShortlinkRepository $shortlinks) {}

    /**
     * Always a 302: browsers cache a 301, so an edited destination would not reach them.
     */
    public function __invoke(string $slug): RedirectResponse
    {
        $shortlink = $this->shortlinks->find($slug);

        if (! $shortlink instanceof Shortlink || ! $shortlink->enabled()) {
            return redirect($this->home());
        }

        $url = $this->resolve($shortlink->destination());

        if ($url === null) {
            return redirect($this->home());
        }

        return redirect($shortlink->utm() ? $this->withUtm($url, $shortlink) : $url);
    }

    private function resolve(?string $destination): ?string
    {
        if ($destination === null || ! str_starts_with($destination, 'entry::')) {
            return $destination;
        }

        return Shortlink::entryOf($destination)?->absoluteUrl();
    }

    private function withUtm(string $url, Shortlink $shortlink): string
    {
        return Uri::of($url)
            ->withQuery([
                'utm_source' => $shortlink->utmSource(),
                'utm_medium' => $shortlink->utmMedium(),
                'utm_campaign' => $shortlink->utmCampaign(),
            ])
            ->value();
    }

    private function home(): string
    {
        return Sites::default()->url();
    }
}
