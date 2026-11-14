<?php

declare(strict_types=1);

return [

    /*
    |----------------------------------------------------------------------
    | Enable Schema.org / JSON-LD
    |----------------------------------------------------------------------
    |
    | Master switch for the JSON-LD graph rendered by {{ juicebox::schema }}.
    | When disabled, the tag renders nothing.
    |
    | Accepted values: true, false
    |
    */

    'enabled' => env('JUICEBOX_SCHEMA_ORG_ENABLED', true),

    /*
    |----------------------------------------------------------------------
    | Breadcrumbs
    |----------------------------------------------------------------------
    |
    | Whether the graph includes a BreadcrumbList node (and the WebPage's
    | `breadcrumb` reference to it). Off by default: a BreadcrumbList that
    | doesn't match a visible breadcrumb trail on the page violates Google's
    | structured-data guidelines, and most sites built on this addon don't
    | render one. Only enable this for a site whose templates actually
    | render a visible breadcrumb nav.
    |
    | Accepted values: true, false
    |
    */

    'breadcrumbs_enabled' => env('JUICEBOX_SCHEMA_ORG_BREADCRUMBS_ENABLED', false),

    /*
    |----------------------------------------------------------------------
    | Breadcrumbs: include home
    |----------------------------------------------------------------------
    |
    | Whether the BreadcrumbList's first item is the homepage, matching
    | `{{ nav:breadcrumbs }}`'s own default. Set this to match whatever
    | `include_home` value the site's visible breadcrumb nav actually uses
    | — the structured breadcrumb must match what's on the page.
    |
    | Accepted values: true, false
    |
    */

    'breadcrumbs_include_home' => env('JUICEBOX_SCHEMA_ORG_BREADCRUMBS_INCLUDE_HOME', true),

];
