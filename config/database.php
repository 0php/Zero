<?php

return [
    'connection' => env('DB_CONNECTION', 'mysql'),

    'mysql' => [
        'driver' => 'mysql',
        'host' =>  env('MYSQL_HOST', '127.0.0.1'),
        'port' => env('MYSQL_PORT', '3306'),
        'database' => env('MYSQL_DATABASE', 'zero'),
        'username' => env('MYSQL_USER', 'root'),
        'password' => env('MYSQL_PASSWORD', ''),
        'charset' => env('MYSQL_CHARSET', 'utf8mb4'),
        'collation' => env('MYSQL_COLLATION', 'utf8mb4_general_ci'),
    ],
    'sqlite' => [
        'driver' => 'sqlite3',
        'database' => env('SQLITE_DATABASE', base('sqlite/zero.sqlite')),
    ],
    'postgres' => [
        'driver' => 'pgsql',
        'host' => env('POSTGRES_HOST', '127.0.0.1'),
        'port' => env('POSTGRES_PORT', '5432'),
        'database' => env('POSTGRES_DATABASE', 'zero'),
        'username' => env('POSTGRES_USER', 'root'),
        'password' => env('POSTGRES_PASSWORD', ''),
        'charset' => env('POSTGRES_CHARSET', 'UTF8'),
    ],
];
