<?php

declare(strict_types=1);

if (!function_exists('cors_allowed_origins')) {
    /**
     * The allowlist of origins permitted to make credentialed cross-origin
     * requests. Reads CORS_ALLOWED_ORIGINS directly so it works both before and
     * after the framework's config layer is available.
     *
     * @return array<int, string>
     */
    function cors_allowed_origins(): array
    {
        $raw = function_exists('env') ? (string) env('CORS_ALLOWED_ORIGINS', '') : '';

        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}

if (!function_exists('cors_emit_headers')) {
    /**
     * Emit the Access-Control-* response headers for a /v1 request.
     *
     * Credentialed CORS (echoing the Origin + Allow-Credentials: true) is only
     * granted to allowlisted origins. Every other origin gets a non-credentialed
     * wildcard so public, no-cookie calls keep working without letting an
     * arbitrary site make credentialed cross-site requests.
     */
    function cors_emit_headers(string $origin, ?string $requestHeaders): void
    {
        $allowed = cors_allowed_origins();

        if ($origin !== '' && in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
        } else {
            header('Access-Control-Allow-Origin: *');
        }

        header('Vary: Origin');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: ' . ($requestHeaders && $requestHeaders !== ''
            ? $requestHeaders
            : 'Content-Type, Authorization, X-Requested-With'));
        header('Access-Control-Max-Age: 86400');
    }
}
