<?php

declare(strict_types=1);

namespace Zero\Lib\Auth;

use App\Models\User;

class Auth
{
    public const COOKIE = 'auth_token';
    public const DEFAULT_TTL = 604800; // 7 days

    /**
     * Issue a JWT for the provided payload and queue it as an HTTP-only cookie.
     */
    public static function login(array $payload, int $ttl = self::DEFAULT_TTL): void
    {
        $configuredTtl = (int) (config('auth.token_ttl') ?? self::DEFAULT_TTL);

        if ($ttl === self::DEFAULT_TTL) {
            $ttl = $configuredTtl;
        }

        $ttl = max(60, $ttl);

        $token = Jwt::encode($payload, $ttl);
        self::queueCookie($token, $ttl);
        $_COOKIE[self::COOKIE] = $token;
    }

    /**
     * Remove the authentication token cookie.
     */
    public static function logout(): void
    {
        $token = $_COOKIE[self::COOKIE] ?? null;

        if (is_string($token) && $token !== '') {
            $payload = Jwt::decode($token);

            // Revoke the token server side so a copied/stolen cookie stops working right away.
            if ($payload !== null && isset($payload['exp'])) {
                self::revokeToken($token, (int) $payload['exp']);
            }
        }

        self::queueCookie('', -3600);
        unset($_COOKIE[self::COOKIE]);
    }

    /**
     * Invalidate every token issued to the user until now (e.g. after a password change or reset).
     */
    public static function revokeAllFor(int|string $userId): void
    {
        self::writeRevocation('user_' . sha1((string) $userId), (string) time(), time() + max(60, (int) (config('auth.token_ttl') ?? self::DEFAULT_TTL)));
    }

    /**
     * Retrieve the decoded token payload for the current user.
     */
    public static function user(): mixed
    {
        $payload = self::validPayload();

        if ($payload === null) {
            self::queueCookie('', -3600);
            unset($_COOKIE[self::COOKIE]);
            return false;
        }

        return User::query()->find($payload['sub']) ?: null;
    }

    /**
     * Verified, non-revoked token payload for the current request.
     */
    protected static function validPayload(): ?array
    {
        $token = $_COOKIE[self::COOKIE] ?? null;
        $payload = Jwt::decode(is_string($token) ? $token : null);

        if ($payload === null || !isset($payload['sub'])) {
            return null;
        }

        if (self::readRevocation('token_' . hash('sha256', (string) $token)) !== null) {
            return null;
        }

        $validAfter = self::readRevocation('user_' . sha1((string) $payload['sub']));

        if ($validAfter !== null && (int) ($payload['iat'] ?? 0) < (int) $validAfter) {
            return null;
        }

        return $payload;
    }

    protected static function revokeToken(string $token, int $expiresAt): void
    {
        if ($expiresAt > time()) {
            self::writeRevocation('token_' . hash('sha256', $token), '1', $expiresAt);
        }
    }

    protected static function revocationDirectory(): string
    {
        return rtrim(storage_path('cache/auth_revocations'), '/\\');
    }

    protected static function writeRevocation(string $key, string $value, int $expiresAt): void
    {
        $dir = self::revocationDirectory();

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents($dir . DIRECTORY_SEPARATOR . $key, $expiresAt . '|' . $value, LOCK_EX);

        // Opportunistic cleanup of expired entries.
        if (random_int(1, 50) === 1) {
            foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                $content = (string) @file_get_contents($file);
                if ((int) strtok($content, '|') < time()) {
                    @unlink($file);
                }
            }
        }
    }

    protected static function readRevocation(string $key): ?string
    {
        $file = self::revocationDirectory() . DIRECTORY_SEPARATOR . $key;

        if (!is_file($file)) {
            return null;
        }

        $content = (string) @file_get_contents($file);
        [$expiresAt, $value] = array_pad(explode('|', $content, 2), 2, '');

        if ((int) $expiresAt < time()) {
            @unlink($file);
            return null;
        }

        return $value;
    }

    /**
     * Convenience accessor for the subject identifier.
     *
     * Reads the subject straight from the verified token payload rather than
     * from the User model (which has no `sub` key), so it returns the
     * authenticated user's id, or null when there is no valid token.
     */
    public static function id(): mixed
    {
        $payload = self::validPayload();

        return $payload['sub'] ?? null;
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
