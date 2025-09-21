<?php

return [
    'driver' => env('SESSION_DRIVER', 'database'), // database, cookie
    'table' => env('SESSION_TABLE', 'sessions'),
    'lifetime' => (int) env('SESSION_LIFETIME', 120), // minutes
    'cookie' => env('SESSION_COOKIE', 'zero_session'),
    'path' => env('SESSION_PATH', '/'),
    'domain' => env('SESSION_DOMAIN', null) ?: null,
    'secure' => filter_var(env('SESSION_SECURE_COOKIE', false), FILTER_VALIDATE_BOOL),
    'http_only' => true,
    'same_site' => strtolower(env('SESSION_SAME_SITE', 'lax')), // lax, strict, none
];
