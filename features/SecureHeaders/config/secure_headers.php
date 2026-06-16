<?php

return [

    /*
    |----------------------------------------------------------------------
    | Enable Secure Headers
    |----------------------------------------------------------------------
    |
    | Master switch for all secure HTTP response headers. When disabled,
    | no headers from this config will be sent.
    |
    | Accepted values: true, false
    |
    */

    'enabled' => env('JUICEBOX_SECURE_HEADERS_ENABLED', true),

    /*
    |----------------------------------------------------------------------
    | Strict-Transport-Security
    |----------------------------------------------------------------------
    |
    | Instructs browsers to only access the site over HTTPS for the
    | specified duration. Only sent on secure (HTTPS) requests.
    |
    | Example: 'max-age=63072000; includeSubDomains; preload'
    |
    | Accepted values: as in example or false
    |
    */

    'strict_transport_security' => 'max-age=31536000',

    /*
    |----------------------------------------------------------------------
    | X-Frame-Options
    |----------------------------------------------------------------------
    |
    | Controls whether the page can be embedded in an iframe. Prevents
    | clickjacking attacks.
    |
    | Accepted values: 'DENY', 'SAMEORIGIN', false
    |
    */

    'x_frame_options' => 'SAMEORIGIN',

    /*
    |----------------------------------------------------------------------
    | X-Content-Type-Options
    |----------------------------------------------------------------------
    |
    | Prevents browsers from MIME-sniffing a response away from the
    | declared Content-Type.
    |
    | Accepted value: 'nosniff', false
    |
    */

    'x_content_type_options' => 'nosniff',

    /*
    |----------------------------------------------------------------------
    | Referrer-Policy
    |----------------------------------------------------------------------
    |
    | Controls how much referrer information is included with requests.
    |
    | Example values: 'no-referrer', 'origin', 'strict-origin-when-cross-origin', false
    |
    */

    'referrer_policy' => 'strict-origin-when-cross-origin',

];
