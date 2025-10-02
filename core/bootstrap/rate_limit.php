<?php

/**
 * Global Rate Limiting Bootstrap
 * 
 * Applies rate limiting to all incoming requests before routing.
 * Configurable via config/rate_limit.php and .env variables.
 */

use App\Services\RateLimiter;
use Zero\Lib\Http\Request;
use Zero\Lib\Http\Response;

$globalConfig = config('rate_limit.global');

if (!$globalConfig || !($globalConfig['enabled'] ?? false)) {
    return;
}

// Check if current route is excluded
$currentUri = '/' . trim(explode('?', $_SERVER['REQUEST_URI'] ?? '/')[0], '/');
$excludedRoutes = $globalConfig['exclude'] ?? [];

foreach ($excludedRoutes as $pattern) {
    $pattern = '/' . trim($pattern, '/');
    
    // Exact match
    if ($pattern === $currentUri) {
        return;
    }
    
    // Wildcard match
    if (str_contains($pattern, '*')) {
        $regex = '#^' . str_replace(['\*', '/'], ['.*', '\/'], preg_quote($pattern, '#')) . '$#';
        if (preg_match($regex, $currentUri)) {
            return;
        }
    }
}

$max = (int) ($globalConfig['max_attempts'] ?? 60);
$decay = (int) ($globalConfig['decay_seconds'] ?? 60);
$strategy = (string) ($globalConfig['key_strategy'] ?? 'ip');

$headersCfg = config('rate_limit.headers');
$request = Request::capture();
$limiter = new RateLimiter();
$key = $limiter->keyFor($request, $strategy, 'global');

[$allowed, $remaining, $retryAfter, $resetAt] = $limiter->hit($key, $max, $decay);

// Always attach rate limit headers
header(($headersCfg['limit'] ?? 'X-RateLimit-Limit') . ': ' . $max, true);
header(($headersCfg['remaining'] ?? 'X-RateLimit-Remaining') . ': ' . max(0, $remaining), true);
header(($headersCfg['reset'] ?? 'X-RateLimit-Reset') . ': ' . $resetAt, true);

if (!$allowed) {
    header(($headersCfg['retry_after'] ?? 'Retry-After') . ': ' . $retryAfter, true);
    
    $response = Response::json([
        'message' => 'Too Many Requests.',
    ], 429);
    
    $response->send();
    exit;
}
