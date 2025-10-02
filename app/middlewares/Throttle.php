<?php

namespace App\Middlewares;

use App\Services\RateLimiter;
use Zero\Lib\Http\Request;
use Zero\Lib\Http\Response;

class Throttle
{
    /**
     * Handle an incoming request.
     * Optional signature: handle(Request $request, int $maxAttempts = null, int $decaySeconds = null, string $strategy = null)
     */
    public function handle(Request $request, ?int $maxAttempts = null, ?int $decaySeconds = null, ?string $strategy = null): ?Response
    {
        $max = $maxAttempts ?? (int) (config('rate_limit.throttle.max_attempts') ?? 60);
        $decay = $decaySeconds ?? (int) (config('rate_limit.throttle.decay_seconds') ?? 60);
        $strategy = $strategy ?: (string) (config('rate_limit.throttle.key_strategy') ?? 'ip');

        $headersCfg = config('rate_limit.headers');
        $limiter = new RateLimiter();
        $key = $limiter->keyFor($request, $strategy, 'throttle');

        [$allowed, $remaining, $retryAfter, $resetAt] = $limiter->hit($key, $max, $decay);

        // Always attach rate limit headers
        header(($headersCfg['limit'] ?? 'X-RateLimit-Limit') . ': ' . $max, true);
        header(($headersCfg['remaining'] ?? 'X-RateLimit-Remaining') . ': ' . max(0, $remaining), true);
        header(($headersCfg['reset'] ?? 'X-RateLimit-Reset') . ': ' . $resetAt, true);
        if (!$allowed) {
            header(($headersCfg['retry_after'] ?? 'Retry-After') . ': ' . $retryAfter, true);
            return Response::json([
                'message' => 'Too Many Attempts.',
            ], 429);
        }

        return null;
    }
}
