<?php

use Zero\Lib\Log\Handlers\StderrHandler;
use Zero\Lib\Log\Handlers\InternalHandler;

return [
    'default' => env('LOG_DRIVER', 'file'),

    'channels' => [
        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/logs'),
        ],
        'database' => [
            'driver' => 'database',
            'table' => env('LOG_TABLE', 'logs'),
            'fallback' => env('LOG_FALLBACK', 'file'),
        ],
        'stderr' => [
            'driver' => 'custom',
            'handler' => StderrHandler::class,
        ],
        'internal' => [
            'driver' => 'custom',
            'handler' => InternalHandler::class,
            'stream' => 'php://stdout',
        ],

        'stack' => [
            'driver' => 'stack',
            'channels' => array_filter(array_map('trim', explode(',', env('LOG_STACK', 'internal,stderr,file')))),
        ],
    ],
];
