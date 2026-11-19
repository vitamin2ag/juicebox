<?php

declare(strict_types=1);

return [

    /*
    |----------------------------------------------------------------------
    | Enable Search & Replace
    |----------------------------------------------------------------------
    |
    | Master switch for the Search & Replace feature, off by default.
    |
    | Accepted values: true, false
    |
    */

    'enabled' => env('JUICEBOX_SEARCH_REPLACE_ENABLED', false),

    /*
    |----------------------------------------------------------------------
    | Schedule
    |----------------------------------------------------------------------
    |
    | When Laravel's scheduler applies the pending replacements. The server
    | needs the cron entry of the scheduler, which runs
    | php artisan schedule:run.
    |
    | Accepted values: a cron expression, or false for no scheduled run
    |
    */

    'schedule' => env('JUICEBOX_SEARCH_REPLACE_SCHEDULE', '* * * * *'),

];
