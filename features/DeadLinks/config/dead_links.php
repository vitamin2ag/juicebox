<?php

declare(strict_types=1);

return [

    /*
    |----------------------------------------------------------------------
    | Enable Dead Links
    |----------------------------------------------------------------------
    |
    | Master switch for the Dead Links feature, off by default.
    |
    | Accepted values: true, false
    |
    */

    'enabled' => env('JUICEBOX_DEAD_LINKS_ENABLED', false),

    /*
    |----------------------------------------------------------------------
    | Schedule
    |----------------------------------------------------------------------
    |
    | When Laravel's scheduler runs the check. The server needs the cron
    | entry of the scheduler, which runs php artisan schedule:run.
    |
    | Accepted values: a cron expression, or false for no scheduled check
    |
    */

    'schedule' => env('JUICEBOX_DEAD_LINKS_SCHEDULE', '0 3 * * *'),

    /*
    |----------------------------------------------------------------------
    | Timeout
    |----------------------------------------------------------------------
    |
    | Seconds an external link may take to answer before it counts as
    | broken.
    |
    | Accepted values: a number of seconds
    |
    */

    'timeout' => 10,

    /*
    |----------------------------------------------------------------------
    | Exclude
    |----------------------------------------------------------------------
    |
    | Links that start with one of these prefixes are never checked.
    |
    | Example: ['https://intranet.example.com/', '/members/']
    |
    | Accepted values: an array of URL prefixes
    |
    */

    'exclude' => [],

];
