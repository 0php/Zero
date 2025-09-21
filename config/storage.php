<?php

declare(strict_types=1);

return [
    'default' => env('STORAGE_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => env('STORAGE_LOCAL_ROOT', storage_path()),
        ],
        'public' => [
            'driver' => 'local',
            'root' => env('STORAGE_PUBLIC_ROOT', storage_path('public')),
        ],
    ],

    'links' => [
        public_path('storage') => 'public',
    ],
];
