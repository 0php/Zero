<?php
namespace Zero\Lib;

use Zero\Lib\Crypto;

class Cookie
{
    /**
     * Set an encrypted cookie.
     *
     * @param string $name The name of the cookie.
     * @param string $value The value to be stored.
     * @param int $expiry The expiration time in seconds. Default is 3600 (1 hour).
     * @param string $path The path on the server in which the cookie will be available. Default is '/'.
     * @param string $domain The domain that the cookie is available to. Default is ''.
     * @param bool $secure Indicates that the cookie should only be transmitted over a secure HTTPS connection. Default is false.
     * @param bool $httpOnly When true, the cookie will be made accessible only through the HTTP protocol. Default is true.
     * @throws \Exception
     */
    public static function set(
        string $name,
        string $value,
        int $expiry = 3600,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true
    ): void {
        $encryptedValue = Crypto::encrypt($value);
        setcookie($name, $encryptedValue, [
            'expires' => time() + $expiry,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Get the value of an encrypted cookie.
     *
     * @param string $name The name of the cookie.
     * @return string|null The decrypted value of the cookie or null if not found.
     * @throws \Exception
     */
    public static function get(string $name): ?string
    {
        if (!isset($_COOKIE[$name])) {
            return null;
        }

        return Crypto::decrypt($_COOKIE[$name]);
    }

    /**
     * Delete a cookie.
     *
     * @param string $name The name of the cookie.
     * @param string $path The path on the server in which the cookie was available. Default is '/'.
     * @param string $domain The domain that the cookie was available to. Default is ''.
     * @param bool $secure Indicates that the cookie should only be transmitted over a secure HTTPS connection. Default is false.
     * @param bool $httpOnly When true, the cookie will be made accessible only through the HTTP protocol. Default is true.
     */
    public static function delete(
        string $name,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true
    ): void {
        setcookie($name, '', [
            'expires' => time() - 3600,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => 'Lax',
        ]);
    }
}
