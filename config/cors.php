<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://haneri.com',
        'https://www.haneri.com',
        'http://localhost',
        'http://localhost:8000',
        'http://127.0.0.1:8000',
    ],
    // During testing, you can even do:
    // 'allowed_origins' => ['*'],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
