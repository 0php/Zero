<?php

return [
    // Global throttle - applied automatically to all requests before routing
    'global' => [
        'enabled' => filter_var(env('RATE_LIMIT_ENABLED', 'false'), FILTER_VALIDATE_BOOL),
        'max_attempts' => (int) env('RATE_LIMIT_MAX_ATTEMPTS', 60),
        'decay_seconds' => (int) env('RATE_LIMIT_DECAY_SECONDS', 60),
        // Key strategy: ip|route|ip_route
        'key_strategy' => env('RATE_LIMIT_KEY_STRATEGY', 'ip'),
        
        // Exclude specific routes from global rate limiting
        // Supports exact matches and wildcards (*)
        'exclude' => [
            // '/login'
            // '/health',
            // '/api/webhooks/*',
            // '/public/*',
        ],
    ],

    // Default throttle settings (e.g., 60 requests per 60 seconds)
    'throttle' => [
        'max_attempts' => 60,
        'decay_seconds' => 60,
        // Key strategy: ip|route|ip_route
        'key_strategy' => 'ip',
    ],

    // Generic rate limit settings (e.g., 100 requests per 60 seconds)
    'rate_limit' => [
        'max_requests' => 100,
        'window_seconds' => 60,
        // Key strategy: ip|route|ip_route
        'key_strategy' => 'ip',
    ],

    // Response headers
    'headers' => [
        'limit' => 'X-RateLimit-Limit',
        'remaining' => 'X-RateLimit-Remaining',
        'retry_after' => 'Retry-After',
        'reset' => 'X-RateLimit-Reset',
    ],

    // Storage driver (currently only file) and relative directory under storage/cache
    'storage' => [
        'driver' => 'file',
        'directory' => 'rate_limit',
    ],
];
