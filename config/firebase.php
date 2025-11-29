<?php

return [

    'default' => env('FIREBASE_PROJECT_ID', 'default'),

    'projects' => [
        env('FIREBASE_PROJECT_ID', 'default') => [
            'credentials' => storage_path(env('FIREBASE_CREDENTIALS')),
        ],
    ],

];
