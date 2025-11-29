<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // 👇 Allow your frontend domain(s)
    'allowed_origins' => [
        'https://haneri.com',
        'https://www.haneri.com',
    ],

    // or for quick testing ONLY (not recommended for prod):
    // 'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // If you ever send cookies / Authorization header cross-domain, set this true
    'supports_credentials' => false,
];
