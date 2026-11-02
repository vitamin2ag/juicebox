<?php

declare(strict_types=1);

return [

    /*
    |----------------------------------------------------------------------
    | Enable Shortlinks
    |----------------------------------------------------------------------
    |
    | Master switch for the Shortlinks feature, off by default.
    |
    | Accepted values: true, false
    |
    */

    'enabled' => env('JUICEBOX_SHORTLINKS_ENABLED', false),

    /*
    |----------------------------------------------------------------------
    | Store
    |----------------------------------------------------------------------
    |
    | Directory of the shortlink YAML files, one file per shortlink, named
    | by its slug.
    |
    | Accepted values: a directory relative to the project root
    |
    */

    'store' => 'content/shortlinks',

];
