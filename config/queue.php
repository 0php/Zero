<?php

return [
    /*
     | Default connection used when no connection name is given to dispatch()
     | or Queue::push(). Names map to entries under "connections" below.
     */
    'default' => env('QUEUE_CONNECTION', 'sync'),

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => null,         // null = use the default DB connection
            'table' => 'jobs',
            'failed_table' => 'failed_jobs',
            'queue' => 'default',
            'retry_after' => 90,          // seconds before a stuck reserved job is reclaimed
        ],
    ],

    /*
     | Where failed jobs go. Currently only the database driver supports a
     | failed-jobs store.
     */
    'failed' => [
        'driver' => 'database',
        'connection' => null,
        'table' => 'failed_jobs',
    ],
];
