<?php

namespace App\Middlewares;

use App\Services\RateLimiter;
use Zero\Lib\Http\Request;
use Zero\Lib\Http\Response;

class RateLimit
{
    /**
     * Handle an incoming request.
     * Optional signature: handle(Request $request, int $maxRequests = null, int $windowSeconds = null, string $strategy = null)
     */
    public function handle(Request $request, ?int $maxRequests = null, ?int $windowSeconds = null, ?string $strategy = null): ?Response
    {
        $max = $maxRequests ?? (int) (config('rate_limit.rate_limit.max_requests') ?? 100);
        $window = $windowSeconds ?? (int) (config('rate_limit.rate_limit.window_seconds') ?? 60);
        $strategy = $strategy ?: (string) (config('rate_limit.rate_limit.key_strategy') ?? 'ip');

        $headersCfg = config('rate_limit.headers');
        $limiter = new RateLimiter();
        $key = $limiter->keyFor($request, $strategy, 'rate');

        [$allowed, $remaining, $retryAfter, $resetAt] = $limiter->hit($key, $max, $window);

        header(($headersCfg['limit'] ?? 'X-RateLimit-Limit') . ': ' . $max, true);
        header(($headersCfg['remaining'] ?? 'X-RateLimit-Remaining') . ': ' . max(0, $remaining), true);
        header(($headersCfg['reset'] ?? 'X-RateLimit-Reset') . ': ' . $resetAt, true);
        if (!$allowed) {
            header(($headersCfg['retry_after'] ?? 'Retry-After') . ': ' . $retryAfter, true);
            return Response::json([
                'message' => 'Too Many Requests.',
            ], 429);
        }

        return null;
    }
}
