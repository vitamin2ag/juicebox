<?php

declare(strict_types=1);

return [

    /*
    |----------------------------------------------------------------------
    | Enable Redirects
    |----------------------------------------------------------------------
    |
    | Master switch for the Redirects feature.
    |
    | Accepted values: true, false
    |
    */

    'enabled' => env('JUICEBOX_REDIRECTS_ENABLED', true),

    /*
    |----------------------------------------------------------------------
    | Store
    |----------------------------------------------------------------------
    |
    | Directory of the redirect YAML files, one folder per site. The default
    | is where rias/statamic-redirect keeps them, so they keep working.
    |
    | Accepted values: a directory relative to the project root
    |
    */

    'store' => 'content/redirects',

];
