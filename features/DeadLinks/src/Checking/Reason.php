<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\DeadLinks\Checking;

/**
 * Why a link is broken, as a code the control panel translates.
 */
enum Reason: string
{
    case MissingEntry = 'missing_entry';

    /**
     * A draft, or a dated entry that is not public yet or any more.
     */
    case UnpublishedEntry = 'unpublished_entry';

    case MissingAsset = 'missing_asset';

    /**
     * The server answered 404 or 410.
     */
    case PageNotFound = 'page_not_found';

    /**
     * The server answered with another 4xx, except 401, 403 and 429, or a 5xx.
     */
    case HttpError = 'http_error';

    /**
     * No answer: the host is unknown, the connection failed or the timeout ran out.
     */
    case ConnectionFailed = 'connection_failed';

    /**
     * Neither a reference nor a path nor an http(s) URL with a valid host, such as `www.example.com` or `https::/example.com`.
     */
    case InvalidUrl = 'invalid_url';
}
