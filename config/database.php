<?php

return [
    'connection' => env('DB_CONNECTION', 'mysql'),
    'charset' => 'utf8mb4',

    'mysql' => [
        'driver' => 'mysql',
        'host' =>  env('MYSQL_HOST', '127.0.0.1'),
        'port' => env('MYSQL_PORT', '3306'),
        'database' => env('MYSQL_DATABASE', 'zero'),
        'username' => env('MYSQL_USER', 'root'),
        'password' => env('MYSQL_PASSWORD', ''),
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
    ],
];