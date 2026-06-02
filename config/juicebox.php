<?php

return [
    'secure_headers' => [
        'strict_transport_security' => 'max-age=31536000',
        'x_frame_options' => 'SAMEORIGIN',
        'x_content_type_options' => 'nosniff',
        'referrer_policy' => 'strict-origin-when-cross-origin',
    ],
];
