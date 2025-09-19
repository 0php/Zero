<?php

declare(strict_types=1);

namespace Zero\Lib\Auth;

use Exception;
use RuntimeException;
use Zero\Lib\Crypto;

class Jwt
{
    /**
     * Encode the given payload into an encrypted token.
     */
    public static function encode(array $payload, int $ttl = 3600): string
    {
        $claims = $payload;
        $claims['iat'] = $claims['iat'] ?? time();
        $claims['exp'] = $claims['exp'] ?? ($claims['iat'] + $ttl);

        $json = json_encode($claims, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Failed to encode JWT payload.');
        }

        return Crypto::encrypt($json);
    }

    /**
     * Decode a token into its payload if valid, otherwise null.
     */
    public static function decode(?string $token): ?array
    {
        if ($token === null || $token === '') {
            return null;
        }

        try {
            $json = Crypto::decrypt($token);
        } catch (Exception $exception) {
            return null;
        }

        $data = json_decode($json, true);

        if (!is_array($data)) {
            return null;
        }

        $expires = $data['exp'] ?? null;

        if (!is_numeric($expires) || (int) $expires < time()) {
            return null;
        }

        return $data;
    }
}
