<?php

declare(strict_types=1);

namespace Zero\Lib\Auth;

use App\Models\User;

class Auth
{
    public const COOKIE = 'auth_token';
    public const DEFAULT_TTL = 3600;

    /**
     * Issue a JWT for the provided payload and queue it as an HTTP-only cookie.
     */
    public static function login(array $payload, int $ttl = self::DEFAULT_TTL): void
    {
        $token = Jwt::encode($payload, $ttl);
        self::queueCookie($token, $ttl);
        $_COOKIE[self::COOKIE] = $token;
    }

    /**
     * Remove the authentication token cookie.
     */
    public static function logout(): void
    {
        self::queueCookie('', -3600);
        unset($_COOKIE[self::COOKIE]);
    }

    /**
     * Retrieve the decoded token payload for the current user.
     */
    public static function user(): mixed
    {
        $token = $_COOKIE[self::COOKIE] ?? null;
        $payload = Jwt::decode($token);

        if ($payload === null) {
            self::logout();
            return false;
        }

        return User::query()->find($payload['sub']) ?: null;
    }

    /**
     * Convenience accessor for the subject identifier.
     */
    public static function id(): mixed
    {
        $user = self::user();

        return $user['sub'] ?? null;
    }

    protected static function queueCookie(string $value, int $ttl): void
    {
        $expires = time() + $ttl;
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        setcookie(self::COOKIE, $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
