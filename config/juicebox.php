<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Secure Headers
    |--------------------------------------------------------------------------
    |
    | These values are applied as HTTP response headers by the SetSecureHeaders
    | middleware. Set any header to false to disable it entirely.
    |
    */

    'secure_headers' => [

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
        | Accepted values: 'DENY', 'SAMEORIGIN'
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
        | Accepted value: 'nosniff'
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
        | Example values: 'no-referrer', 'origin', 'strict-origin-when-cross-origin'
        |
        */

        'referrer_policy' => 'strict-origin-when-cross-origin',

    ],

];
