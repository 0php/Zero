<?php
namespace Zero\Lib;

class Crypto
{
    /**
     * Get the salt value, defaulting to env('APP_KEY').
     *
     * @return string
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
     * Generate a bcrypt hash with a custom salt.
     *
     * @param string $value The value to be hashed.
     * @return string The hashed value.
     * @throws \Exception
     */
    public static function hash(string $value): string
    {
        $salt = self::getSalt();
        $saltedValue = $salt . $value;

        return password_hash($saltedValue, PASSWORD_BCRYPT);
    }

    /**
     * Validate if a plain value matches a salted bcrypt hash.
     *
     * @param string $plainValue The plain value to validate.
     * @param string $hashedValue The hashed value to compare against.
     * @return bool True if the values match, false otherwise.
     * @throws \Exception
     */
    public static function validate(string $plainValue, string $hashedValue): bool
    {
        $salt = self::getSalt();
        $saltedValue = $salt . $plainValue;

        return password_verify($saltedValue, $hashedValue);
    }

    /**
     * Encrypt the given value using OpenSSL.
     *
     * @param string $value The value to encrypt.
     * @return string The encrypted value (Base64-encoded).
     * @throws \Exception
     */
    public static function encrypt(string $value): string
    {
        $salt = self::getSalt();
        $iv = random_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $salt, 0, $iv);

        if ($encrypted === false) {
            throw new \Exception('Failed to encrypt the value.');
        }

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt the given value using OpenSSL.
     *
     * @param string $value The encrypted value (Base64-encoded).
     * @return string The decrypted value.
     * @throws \Exception
     */
    public static function decrypt(string $value): string
    {
        $salt = self::getSalt();
        $data = base64_decode($value);

        if ($data === false) {
            throw new \Exception('Failed to decode the encrypted value.');
        }

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
