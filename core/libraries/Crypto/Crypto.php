<?php
namespace Zero\Lib;

class Crypto
{
    /** Authenticated cipher used for encrypt()/decrypt(). */
    private const CIPHER = 'aes-256-gcm';
    private const GCM_IV_LENGTH = 12;
    private const GCM_TAG_LENGTH = 16;

    /**
     * Get the raw APP_KEY string (used for password hashing salt).
     *
     * @throws \Exception
     */
    private static function getSalt(): string
    {
        $salt = env('APP_KEY');

        if (!is_string($salt) || trim($salt) === '') {
            throw new \Exception('APP_KEY is not defined in the environment variables.');
        }

        return (string) $salt;
    }

    /**
     * Derive a 32-byte encryption key from APP_KEY.
     *
     * Supports the Laravel-style "base64:" prefix (decoded to raw bytes). Any
     * key material that is not already exactly 32 bytes is run through SHA-256
     * so the AES-256 key length is always correct regardless of how APP_KEY is
     * formatted.
     *
     * @throws \Exception
     */
    private static function encryptionKey(): string
    {
        $key = self::getSalt();

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded !== false) {
                $key = $decoded;
            }
        }

        if (strlen($key) === 32) {
            return $key;
        }

        return hash('sha256', $key, true);
    }

    /**
     * Generate a bcrypt password hash.
     *
     * Produces a plain bcrypt hash (Laravel-compatible, no key prefix) so the
     * output matches the hashes already stored in production. Validation still
     * accepts the older APP_KEY-salted format, see validate().
     *
     * @param string $value The value to be hashed.
     * @return string The hashed value.
     */
    public static function hash(string $value): string
    {
        return password_hash($value, PASSWORD_BCRYPT);
    }

    /**
     * Validate a plain value against a bcrypt hash.
     *
     * Backward compatible across both hashing schemes this codebase has used:
     *   1. Plain bcrypt (Laravel-style, no key prefix) — current and the
     *      scheme production data was created with.
     *   2. APP_KEY-salted bcrypt (password_hash(APP_KEY . $value)) — used
     *      during an intermediate window.
     * Either stored format verifies, so no existing user is locked out.
     *
     * @param string $plainValue The plain value to validate.
     * @param string $hashedValue The stored bcrypt hash to compare against.
     * @return bool True if the value matches under either scheme.
     */
    public static function validate(string $plainValue, string $hashedValue): bool
    {
        if ($hashedValue === '') {
            return false;
        }

        // Plain bcrypt (production hashes).
        if (password_verify($plainValue, $hashedValue)) {
            return true;
        }

        // Legacy APP_KEY-salted bcrypt.
        try {
            $salt = self::getSalt();
        } catch (\Throwable $e) {
            return false;
        }

        return password_verify($salt . $plainValue, $hashedValue);
    }

    /**
     * Encrypt and authenticate a value using AES-256-GCM.
     *
     * Output is base64(iv || tag || ciphertext). GCM provides integrity, so a
     * tampered ciphertext is rejected by decrypt() rather than silently
     * decrypting to attacker-influenced plaintext.
     *
     * @throws \Exception
     */
    public static function encrypt(string $value): string
    {
        $key = self::encryptionKey();
        $iv = random_bytes(self::GCM_IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $value,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::GCM_TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new \Exception('Failed to encrypt the value.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt a value produced by encrypt().
     *
     * Verifies the GCM auth tag before returning plaintext. For backward
     * compatibility with tokens/cookies issued before the GCM migration, this
     * falls back to the legacy AES-256-CBC format when the GCM tag does not
     * authenticate. The legacy path can be removed once all old cookies/tokens
     * have expired.
     *
     * @throws \Exception
     */
    public static function decrypt(string $value): string
    {
        $data = base64_decode($value, true);

        if ($data === false || $data === '') {
            throw new \Exception('Failed to decode the encrypted value.');
        }

        if (strlen($data) > self::GCM_IV_LENGTH + self::GCM_TAG_LENGTH) {
            $key = self::encryptionKey();
            $iv = substr($data, 0, self::GCM_IV_LENGTH);
            $tag = substr($data, self::GCM_IV_LENGTH, self::GCM_TAG_LENGTH);
            $ciphertext = substr($data, self::GCM_IV_LENGTH + self::GCM_TAG_LENGTH);

            $decrypted = openssl_decrypt(
                $ciphertext,
                self::CIPHER,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            if ($decrypted !== false) {
                return $decrypted;
            }
        }

        return self::decryptLegacyCbc($data);
    }

    /**
     * Legacy AES-256-CBC decryption for values encrypted before the GCM
     * migration. The raw APP_KEY string was used directly as the key.
     *
     * @throws \Exception
     */
    private static function decryptLegacyCbc(string $data): string
    {
        $salt = self::getSalt();
        $ivLength = openssl_cipher_iv_length('AES-256-CBC');
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);

        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $salt, 0, $iv);

        if ($decrypted === false) {
            throw new \Exception('Failed to decrypt the value.');
        }

        return $decrypted;
    }
}
