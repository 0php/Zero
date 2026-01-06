<?php

declare(strict_types=1);

$appUrl = rtrim((string) env('APP_URL', 'http://127.0.0.1:8000'), '/');
$publicRootEnv = env('STORAGE_PUBLIC_ROOT', '');
$privateRootEnv = env('STORAGE_PRIVATE_ROOT', '');
$publicRoot = (is_string($publicRootEnv) && trim($publicRootEnv) !== '')
    ? $publicRootEnv
    : storage_path('app/public');
$privateRoot = (is_string($privateRootEnv) && trim($privateRootEnv) !== '')
    ? $privateRootEnv
    : storage_path('app/private');

return [
    'default' => env('STORAGE_DISK', 'public'),

    'disks' => [
        'public' => [
            'driver' => 'local',
            'root' => $publicRoot,
            'url' => $appUrl . '/storage',
            'visibility' => 'public',
        ],

        'private' => [
            'driver' => 'local',
            'root' => $privateRoot,
            'url' => $appUrl . '/files/private',
            'visibility' => 'private',
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('S3_ACCESS_KEY'),
            'secret' => env('S3_SECRET_KEY'),
            'region' => env('S3_REGION', 'us-east-1'),
            'signing_region' => env('S3_SIGNING_REGION'),
            'bucket' => env('S3_BUCKET'),
            'endpoint' => env('S3_ENDPOINT'),
            'path_style' => filter_var(env('S3_PATH_STYLE', true), FILTER_VALIDATE_BOOLEAN),
            'acl' => env('S3_DEFAULT_ACL', 'private'),
            'root' => env('S3_ROOT_PATH', ''),
            'timeout' => (int) env('S3_TIMEOUT', 60),
            'signature_version' => strtolower((string) env('S3_SIGNATURE_VERSION', 'auto')),
            'visibility' => env('S3_VISIBILITY', 'private'),
        ],
    ],

    'links' => [
        public_path('storage') => 'public',
    ],
];
