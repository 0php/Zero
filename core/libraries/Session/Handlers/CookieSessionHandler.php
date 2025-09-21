<?php

declare(strict_types=1);

namespace Zero\Lib\Session\Handlers;

use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;
use Zero\Lib\Crypto;
use Zero\Lib\Log;

class CookieSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private string $payloadCookieName;

    public function __construct(
        private string $cookieName,
        private int $lifetimeSeconds,
        private string $path,
        private ?string $domain,
        private bool $secure,
        private bool $httpOnly,
        private ?string $sameSite
    ) {
        $this->payloadCookieName = $this->cookieName . '_payload';
    }

    public function open($savePath, $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        if (!isset($_COOKIE[$this->payloadCookieName])) {
            return '';
        }

        try {
            $decrypted = Crypto::decrypt((string) $_COOKIE[$this->payloadCookieName]);
            $decoded = json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($decoded)) {
                return '';
            }

            $expiresAt = $decoded['expires_at'] ?? null;

            if (is_numeric($expiresAt) && (int) $expiresAt < time()) {
                $this->forgetPayload();

                return '';
            }

            $payload = $decoded['payload'] ?? '';

            if (!is_string($payload) || $payload === '') {
                return '';
            }

            $data = base64_decode($payload, true);

            return $data === false ? '' : $data;
        } catch (\Throwable $e) {
            Log::error('Cookie session read failed', ['error' => $e->getMessage()]);
            $this->forgetPayload();

            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        if ($data === '') {
            $this->forgetPayload();

            return true;
        }

        $expiresAt = time() + $this->lifetimeSeconds;

        $payload = json_encode([
            'payload' => base64_encode($data),
            'expires_at' => $expiresAt,
        ], JSON_THROW_ON_ERROR);

        try {
            $encrypted = Crypto::encrypt($payload);
            $this->queueCookie($encrypted, $expiresAt);
        } catch (\Throwable $e) {
            Log::error('Cookie session write failed', ['error' => $e->getMessage()]);
            $this->forgetPayload();
        }

        return true;
    }

    public function destroy(string $id): bool
    {
        $this->forgetPayload();

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return 0;
    }

    public function validateId(string $id): bool
    {
        return true;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        if (!isset($_COOKIE[$this->payloadCookieName])) {
            return true;
        }

        try {
            $expiresAt = time() + $this->lifetimeSeconds;
            $decrypted = Crypto::decrypt((string) $_COOKIE[$this->payloadCookieName]);
            $decoded = json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($decoded)) {
                return true;
            }

            $decoded['expires_at'] = $expiresAt;
            $payload = json_encode($decoded, JSON_THROW_ON_ERROR);
            $encrypted = Crypto::encrypt($payload);
            $this->queueCookie($encrypted, $expiresAt);
        } catch (\Throwable $e) {
            Log::error('Cookie session timestamp update failed', ['error' => $e->getMessage()]);
        }

        return true;
    }

    private function queueCookie(string $value, int $expiresAt): void
    {
        $options = [
            'expires' => $expiresAt,
            'path' => $this->path,
            'secure' => $this->secure,
            'httponly' => $this->httpOnly,
        ];

        if ($this->domain !== null) {
            $options['domain'] = $this->domain;
        }

        if ($this->sameSite !== null) {
            $options['samesite'] = $this->sameSite;
        }

        setcookie($this->payloadCookieName, $value, $options);
    }

    private function forgetPayload(): void
    {
        $options = [
            'expires' => time() - 3600,
            'path' => $this->path,
            'secure' => $this->secure,
            'httponly' => $this->httpOnly,
        ];

        if ($this->domain !== null) {
            $options['domain'] = $this->domain;
        }

        if ($this->sameSite !== null) {
            $options['samesite'] = $this->sameSite;
        }

        setcookie($this->payloadCookieName, '', $options);
        unset($_COOKIE[$this->payloadCookieName]);
    }
}
