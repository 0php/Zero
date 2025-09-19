<?php

declare(strict_types=1);

use Zero\Lib\Database;
use Zero\Lib\Session\Handlers\DatabaseSessionHandler;

$sessionConfig = config('session');

$cookieName = $sessionConfig['cookie'] ?? 'zero_session';
$lifetimeMinutes = (int) ($sessionConfig['lifetime'] ?? 120);
$lifetimeSeconds = max(60, $lifetimeMinutes * 60);
$cookiePath = $sessionConfig['path'] ?? '/';
$cookieDomain = $sessionConfig['domain'] ?? null;
$cookieSecure = (bool) ($sessionConfig['secure'] ?? false);
$cookieHttpOnly = (bool) ($sessionConfig['http_only'] ?? true);
$cookieSameSite = strtolower((string) ($sessionConfig['same_site'] ?? 'lax'));

session_name($cookieName);

$cookieParams = [
    'lifetime' => $lifetimeSeconds,
    'path' => $cookiePath,
    'domain' => $cookieDomain,
    'secure' => $cookieSecure,
    'httponly' => $cookieHttpOnly,
];

if (in_array($cookieSameSite, ['lax', 'strict', 'none'], true)) {
    $cookieParams['samesite'] = ucfirst($cookieSameSite);
}

session_set_cookie_params($cookieParams);

if (($sessionConfig['driver'] ?? 'database') === 'database') {
    try {
        Database::query('SELECT 1');

        $handler = new DatabaseSessionHandler($sessionConfig['table'] ?? 'sessions', $lifetimeSeconds);
        session_set_save_handler($handler, true);
    } catch (\Throwable $e) {
        error_log('Database session handler unavailable: ' . $e->getMessage());
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
