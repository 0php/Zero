# Rate Limiting

Zero Framework provides built-in rate limiting and throttling capabilities to protect your application from abuse and ensure fair resource usage.

## Table of Contents

- [Global Rate Limiting](#global-rate-limiting)
- [Per-Route Rate Limiting](#per-route-rate-limiting)
- [Configuration Reference](#configuration-reference)
- [Testing](#testing)
- [Best Practices](#best-practices)
- [Architecture](#architecture)

## Global Rate Limiting

Global rate limiting applies automatically to **all requests** before routing. It's ideal for protecting your entire application with a single configuration.

### Configuration

**1. Enable in `.env`:**
```env
RATE_LIMIT_ENABLED=true
RATE_LIMIT_MAX_ATTEMPTS=60
RATE_LIMIT_DECAY_SECONDS=60
RATE_LIMIT_KEY_STRATEGY=ip
```

**2. Or configure in `config/rate_limit.php`:**
```php
'global' => [
    'enabled' => true,
    'max_attempts' => 60,
    'decay_seconds' => 60,
    'key_strategy' => 'ip',
    
    // Exclude specific routes from global rate limiting
    'exclude' => [
        '/health',
        '/api/webhooks/*',
        '/public/*',
    ],
],
```

### Key Strategies

- **`ip`**: Limit per client IP address (default)
- **`route`**: Limit per route (METHOD + URI)
- **`ip_route`**: Limit per IP + route combination

### Excluding Routes

You can exclude specific routes from global rate limiting in `config/rate_limit.php`:

**Exact match:**
```php
'exclude' => [
    '/health',
    '/status',
],
```

**Wildcard patterns:**
```php
'exclude' => [
    '/api/webhooks/*',      // Excludes /api/webhooks/stripe, /api/webhooks/github, etc.
    '/public/*',            // Excludes all routes under /public/
    '/admin/reports/*',     // Excludes all admin report routes
],
```

**Common use cases:**
- **Health check endpoints**: `/health`, `/ping`, `/status`
- **Webhook receivers**: `/api/webhooks/*` (third-party services like Stripe, GitHub)
- **Public assets**: `/public/*`, `/assets/*`
- **Internal monitoring**: `/metrics`, `/diagnostics`
- **Authentication endpoints**: `/login`, `/register` (if you want to apply custom per-route limits instead)

### How It Works

When enabled, the global rate limiter:
1. Runs **before routing** in `public/index.php`
2. Checks if the current URI matches any excluded patterns
3. Tracks requests in `storage/cache/rate_limit/`
4. Returns `429 Too Many Requests` when limit exceeded
5. Adds response headers to every request

### Response Headers

All responses include:
- `X-RateLimit-Limit`: Maximum requests allowed
- `X-RateLimit-Remaining`: Requests remaining in current window
- `X-RateLimit-Reset`: UNIX timestamp when limit resets
- `Retry-After`: Seconds to wait (only on 429 responses)

### Example Response

**Within limit:**
```
HTTP/1.1 200 OK
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1727843900
```

**Exceeded limit:**
```
HTTP/1.1 429 Too Many Requests
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1727843900
Retry-After: 42
```

## Per-Route Rate Limiting

For granular control, attach rate limiting middleware to specific routes or groups. This is useful when you need different limits for different endpoints.

### Throttle Middleware

Use `Throttle` for general request throttling:

```php
use App\Middlewares\Throttle;
use Zero\Lib\Router;

// 10 requests per 60 seconds by IP
Router::get('/api/search', [SearchController::class, 'index'], [Throttle::class, 10, 60, 'ip']);

// Use config defaults (60 req/60s)
Router::get('/api/data', [ApiController::class, 'index'], Throttle::class);

// Strict login throttling: 5 attempts per 60 seconds
Router::post('/login', [AuthController::class, 'login'], [Throttle::class, 5, 60, 'ip']);
```

### RateLimit Middleware

Use `RateLimit` for API endpoints with higher limits:

```php
use App\Middlewares\RateLimit;

// 100 requests per 300 seconds per IP+Route
Router::get('/api/users', [UsersController::class, 'index'], [RateLimit::class, 100, 300, 'ip_route']);

// 1000 requests per hour (3600 seconds)
Router::get('/api/posts', [PostsController::class, 'index'], [RateLimit::class, 1000, 3600, 'ip']);
```

### Group Middleware

Apply rate limiting to multiple routes at once:

```php
// API routes: 200 requests per minute
Router::group(['prefix' => '/api', 'middleware' => [RateLimit::class, 200, 60, 'ip']], function () {
    Router::get('/users', [UsersController::class, 'index']);
    Router::get('/posts', [PostsController::class, 'index']);
    Router::get('/comments', [CommentsController::class, 'index']);
});

// Auth routes: 10 attempts per 5 minutes
Router::group(['prefix' => '/auth', 'middleware' => [Throttle::class, 10, 300, 'ip']], function () {
    Router::post('/login', [AuthController::class, 'login']);
    Router::post('/register', [RegisterController::class, 'store']);
    Router::post('/password/forgot', [PasswordResetController::class, 'email']);
});
```

## Middleware Signatures

**Throttle:**
```php
handle(Request $request, ?int $maxAttempts = null, ?int $decaySeconds = null, ?string $strategy = null)
```

**RateLimit:**
```php
handle(Request $request, ?int $maxRequests = null, ?int $windowSeconds = null, ?string $strategy = null)
```

## Configuration Reference

**`config/rate_limit.php`:**

```php
return [
    // Global throttle (applied to all requests)
    'global' => [
        'enabled' => false,
        'max_attempts' => 60,
        'decay_seconds' => 60,
        'key_strategy' => 'ip',
        'exclude' => [
            // '/health',
            // '/api/webhooks/*',
        ],
    ],

    // Default throttle settings for Throttle middleware
    'throttle' => [
        'max_attempts' => 60,
        'decay_seconds' => 60,
        'key_strategy' => 'ip',
    ],

    // Default rate limit settings for RateLimit middleware
    'rate_limit' => [
        'max_requests' => 100,
        'window_seconds' => 60,
        'key_strategy' => 'ip',
    ],

    // Response headers
    'headers' => [
        'limit' => 'X-RateLimit-Limit',
        'remaining' => 'X-RateLimit-Remaining',
        'retry_after' => 'Retry-After',
        'reset' => 'X-RateLimit-Reset',
    ],

    // Storage configuration
    'storage' => [
        'driver' => 'file',
        'directory' => 'rate_limit',
    ],
];
```

## Testing

**1. Start the dev server:**
```bash
php zero serve
```

**2. Enable global rate limiting in `.env`:**
```env
RATE_LIMIT_ENABLED=true
RATE_LIMIT_MAX_ATTEMPTS=10
RATE_LIMIT_DECAY_SECONDS=10
```

**3. Test with hey or wrk:**
```bash
# Using hey
hey -n 50 -c 10 http://127.0.0.1:8000/

# Using wrk
wrk -t4 -c20 -d10s http://127.0.0.1:8000/
```

**4. Check headers with curl:**
```bash
curl -i http://127.0.0.1:8000/
```

## Best Practices

### 1. Global vs Per-Route

**Use Global Rate Limiting when:**
- You want baseline protection for all endpoints
- Protecting against general DDoS and abuse
- Simple configuration is preferred
- You have predictable traffic patterns

**Use Per-Route Rate Limiting when:**
- Different endpoints need different limits
- Sensitive operations require stricter limits (login, password reset)
- API endpoints need higher throughput
- You want to exclude certain routes from global limits

**Combine Both:**
```php
// .env: Enable global with moderate limits
RATE_LIMIT_ENABLED=true
RATE_LIMIT_MAX_ATTEMPTS=100
RATE_LIMIT_DECAY_SECONDS=60

// config/rate_limit.php: Exclude auth routes
'exclude' => [
    '/login',
    '/register',
],

// routes/web.php: Apply stricter limits to auth
Router::post('/login', [AuthController::class, 'login'], [Throttle::class, 5, 300, 'ip']);
Router::post('/register', [RegisterController::class, 'store'], [Throttle::class, 3, 300, 'ip']);
```

### 2. Key Strategies

- **`ip`**: Best for public endpoints and general protection
  - Example: Public API, search, browsing
- **`route`**: Best when limiting specific operations regardless of user
  - Example: Expensive reports, batch operations
- **`ip_route`**: Best for API endpoints with different limits per route
  - Example: REST APIs where different endpoints have different costs

### 3. Recommended Limits

**General endpoints:**
- Public pages: 60-100 req/min
- API endpoints: 100-200 req/min
- Search: 10-20 req/min

**Sensitive endpoints:**
- Login: 5-10 attempts per 5-15 minutes
- Password reset: 3-5 attempts per 15-30 minutes
- Registration: 3-5 attempts per 15-30 minutes
- Email verification resend: 3 attempts per 15 minutes

**Expensive operations:**
- Reports: 5-10 req/hour
- Exports: 3-5 req/hour
- Bulk operations: 1-3 req/hour

### 4. Production Checklist

- ✅ Enable global rate limiting with reasonable limits
- ✅ Exclude health check and monitoring endpoints
- ✅ Add stricter limits to authentication endpoints
- ✅ Monitor `429` responses in logs
- ✅ Add `storage/cache/rate_limit` to `.gitignore`
- ✅ Test limits before deploying
- ✅ Document your rate limits in API documentation
- ✅ Consider adding rate limit info to error responses

## Architecture

- **Service:** `App\Services\RateLimiter` - File-based counter with locking
- **Middlewares:** `App\Middlewares\Throttle`, `App\Middlewares\RateLimit`
- **Bootstrap:** `core/bootstrap/rate_limit.php` - Loaded from `public/index.php` before routing
- **Config:** `config/rate_limit.php`
- **Storage:** `storage/cache/rate_limit/` (auto-created)

### Bootstrap Order

Rate limiting loads in `public/index.php` in this order:
1. `core/bootstrap/autoload.php` - Autoloader
2. `core/bootstrap/errors.php` - Error handlers
3. Helpers
4. `core/bootstrap/session.php` - Session
5. `core/bootstrap/rate_limit.php` - **Rate limiting** (before routing)
6. `core/bootstrap.php` - Routes and dispatcher

## Frequently Asked Questions

### How do I disable rate limiting temporarily?

Set `RATE_LIMIT_ENABLED=false` in `.env` or comment out the line in `public/index.php`:
```php
// require_once __DIR__ . '/../core/bootstrap/rate_limit.php';
```

### Can I use different limits for authenticated vs guest users?

Yes, use per-route middleware with different limits:
```php
// Guest routes - stricter limits
Router::group(['middleware' => GuestMiddleware::class], function () {
    Router::post('/login', [AuthController::class, 'login'], [Throttle::class, 5, 300, 'ip']);
});

// Authenticated routes - more lenient
Router::group(['middleware' => AuthMiddleware::class], function () {
    Router::get('/dashboard', [DashboardController::class, 'index'], [RateLimit::class, 200, 60, 'ip']);
});
```

### What happens to the counter files?

- Files are stored in `storage/cache/rate_limit/`
- Each unique key gets its own JSON file
- Files are automatically reset when the time window expires
- Old files can be safely deleted (they'll be recreated as needed)

### How do I monitor rate limiting?

Check your logs for `429` responses or add custom logging:
```php
// In core/bootstrap/rate_limit.php after line 56
if (!$allowed) {
    \Zero\Lib\Log::warning('Rate limit exceeded', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'uri' => $_SERVER['REQUEST_URI'] ?? '/',
        'retry_after' => $retryAfter,
    ]);
    // ... existing code
}
```

### Can I use Redis or database instead of files?

Currently only file-based storage is implemented. To add Redis/database:
1. Extend `App\Services\RateLimiter` with a new storage driver
2. Update `config/rate_limit.php` storage configuration
3. Implement the same locking mechanism for race condition prevention

### Does this work with load balancers?

Yes, but be aware:
- **File-based storage**: Counters are per-server (each server has its own limits)
- **Solution**: Use shared storage (NFS, Redis, database) or implement sticky sessions
- **Alternative**: Use a reverse proxy rate limiter (Nginx, Cloudflare)

## Quick Reference

### Environment Variables

```env
RATE_LIMIT_ENABLED=true              # Enable/disable global rate limiting
RATE_LIMIT_MAX_ATTEMPTS=60           # Maximum requests allowed
RATE_LIMIT_DECAY_SECONDS=60          # Time window in seconds
RATE_LIMIT_KEY_STRATEGY=ip           # ip|route|ip_route
```

### Files

- **Config**: `config/rate_limit.php`
- **Bootstrap**: `core/bootstrap/rate_limit.php`
- **Service**: `app/services/RateLimiter.php`
- **Middlewares**: `app/middlewares/Throttle.php`, `app/middlewares/RateLimit.php`
- **Storage**: `storage/cache/rate_limit/`
- **Docs**: `docs/rate-limiting.md`

### Common Patterns

**Global protection with auth exclusions:**
```php
// .env
RATE_LIMIT_ENABLED=true
RATE_LIMIT_MAX_ATTEMPTS=100
RATE_LIMIT_DECAY_SECONDS=60

// config/rate_limit.php
'exclude' => ['/login', '/register'],

// routes/web.php
Router::post('/login', [AuthController::class, 'login'], [Throttle::class, 5, 300, 'ip']);
```

**API with tiered limits:**
```php
// Public API - 60 req/min
Router::group(['prefix' => '/api/public', 'middleware' => [RateLimit::class, 60, 60, 'ip']], function () {
    Router::get('/posts', [PostsController::class, 'index']);
});

// Authenticated API - 200 req/min
Router::group(['prefix' => '/api/v1', 'middleware' => [AuthMiddleware::class, [RateLimit::class, 200, 60, 'ip']]], function () {
    Router::get('/users', [UsersController::class, 'index']);
});
```

## Notes

- Counters are stored as JSON files with file locking to prevent race conditions
- Each key gets its own file: `storage/cache/rate_limit/{sha1_hash}.json`
- Old counter files are automatically reset when the window expires
- The system is stateless - no database required
- Add `storage/cache/rate_limit` to `.gitignore` (already done)
