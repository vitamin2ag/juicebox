<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\DeadLinks\Checking;

use Illuminate\Support\Str;
use Statamic\Facades\URL;
use Statamic\Sites\Site;

/**
 * The scheme and host that the paths in the content of a site are requested on, such as `https://example.com`.
 */
class SiteDomain
{
    public function of(Site $site): string
    {
        return Str::match('~^https?://[^/]+~i', URL::makeAbsolute($site->url()));
    }
}
