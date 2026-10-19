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

    /*
    |----------------------------------------------------------------------
    | Create Entry Redirects
    |----------------------------------------------------------------------
    |
    | Creates a 301 redirect from the old to the new URI when the URI of a
    | published entry changes, through its slug or its place in a structure.
    |
    | Accepted values: true, false
    |
    */

    'create_entry_redirects' => env('JUICEBOX_REDIRECTS_CREATE_ENTRY_REDIRECTS', true),

];
