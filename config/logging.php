<?php

return [
    'default' => env('LOG_DRIVER', 'file'),

    'channels' => [
        'file' => [
            'path' => storage_path('framework/logs'),
        ],
        'database' => [
            'table' => env('LOG_TABLE', 'logs'),
        ],
    ],
];
