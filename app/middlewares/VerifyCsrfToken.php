<?php

namespace App\Middlewares;

use Zero\Lib\Http\Request;
use Zero\Lib\Http\Response;

/**
 * Rejects state-changing requests (POST/PUT/PATCH/DELETE) that do not carry the session CSRF token,
 * either as the "_token" form field or the "X-CSRF-TOKEN" header. Add {{ csrf_token() }} to forms:
 *
 *   <input type="hidden" name="_token" value="{{ csrf_token() }}">
 */
class VerifyCsrfToken
{
    /**
     * Paths that skip verification (exact match or trailing * wildcard), e.g. '/webhooks/*'.
     *
     * @var string[]
     */
    protected array $except = [];

    public function handle(Request $request): ?Response
    {
        $method = strtoupper($request->method());

        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true) || $this->isExcepted($request)) {
            return null;
        }

        $sent = $request->input('_token') ?? $request->header('x-csrf-token');
        $expected = csrf_token();

        if (is_string($sent) && $sent !== '' && hash_equals($expected, $sent)) {
            return null;
        }

        return Response::make('Page expired. Please go back, refresh and try again.', 419);
    }

    protected function isExcepted(Request $request): bool
    {
        $path = '/' . trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');

        foreach ($this->except as $pattern) {
            $pattern = '/' . trim($pattern, '/');

            if ($pattern === $path || (str_ends_with($pattern, '*') && str_starts_with($path, rtrim($pattern, '*')))) {
                return true;
            }
        }

        return false;
    }
}
