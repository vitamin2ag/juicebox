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

    /*
    |----------------------------------------------------------------------
    | Keep Days
    |----------------------------------------------------------------------
    |
    | How many days the command keeps a batch after it applied or undid
    | it. A batch can be undone only while it is kept; a pending batch or
    | undo is never deleted.
    |
    | Accepted values: a number of days, or false to keep every batch
    |
    */

    'keep_days' => env('JUICEBOX_SEARCH_REPLACE_KEEP_DAYS', 30),

];
