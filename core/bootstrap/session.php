<?php

declare(strict_types=1);

use Zero\Lib\Database;
use Zero\Lib\Session\Handlers\CookieSessionHandler;
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
$sameSiteOption = null;

ini_set('session.gc_maxlifetime', (string) $lifetimeSeconds);
ini_set('session.cookie_lifetime', (string) $lifetimeSeconds);

session_name($cookieName);

$cookieParams = [
    'lifetime' => $lifetimeSeconds,
    'path' => $cookiePath,
    'domain' => $cookieDomain,
    'secure' => $cookieSecure,
    'httponly' => $cookieHttpOnly,
];

if (in_array($cookieSameSite, ['lax', 'strict', 'none'], true)) {
    $sameSiteOption = ucfirst($cookieSameSite);
    $cookieParams['samesite'] = $sameSiteOption;
}

session_set_cookie_params($cookieParams);

$driver = $sessionConfig['driver'] ?? 'database';

if ($driver === 'database') {
    try {
        Database::query('SELECT 1');

        $handler = new DatabaseSessionHandler($sessionConfig['table'] ?? 'sessions', $lifetimeSeconds);
        session_set_save_handler($handler, true);
    } catch (\Throwable $e) {
        error_log('Database session handler unavailable: ' . $e->getMessage());
    }
} elseif ($driver === 'cookie') {
    $handler = new CookieSessionHandler(
        $cookieName,
        $lifetimeSeconds,
        $cookiePath,
        $cookieDomain,
        $cookieSecure,
        $cookieHttpOnly,
        $sameSiteOption
    );

    session_set_save_handler($handler, true);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
    $cookieOptions = [
        'expires' => time() + $lifetimeSeconds,
        'path' => $cookiePath,
        'secure' => $cookieSecure,
        'httponly' => $cookieHttpOnly,
    ];

    if ($cookieDomain !== null) {
        $cookieOptions['domain'] = $cookieDomain;
    }

    if (isset($cookieParams['samesite'])) {
        $cookieOptions['samesite'] = $cookieParams['samesite'];
    }

    setcookie($cookieName, session_id(), $cookieOptions);
}
