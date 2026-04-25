<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Str;

trait Identity
{
    public static function is(string|iterable $pattern, string $value): bool
    {
        $patterns = is_iterable($pattern) ? $pattern : [$pattern];
        foreach ($patterns as $p) {
            if ($p === $value) {
                return true;
            }
            $regex = '#^' . str_replace('\*', '.*', preg_quote($p, '#')) . '\z#u';
            if (preg_match($regex, $value) === 1) {
                return true;
            }
        }
        return false;
    }

    public static function isAscii(string $value): bool
    {
        return ! preg_match('/[^\x00-\x7F]/', $value);
    }

    public static function isJson(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        try {
            json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }
        return true;
    }

    public static function isUrl(string $value, array $protocols = []): bool
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        if ($protocols !== []) {
            $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
            return in_array($scheme, array_map('strtolower', $protocols), true);
        }
        return true;
    }

    public static function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-7][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
    }

    public static function isUlid(string $value): bool
    {
        return preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', $value) === 1;
    }

    public static function isMatch(string|array $pattern, string $value): bool
    {
        foreach ((array) $pattern as $p) {
            if (preg_match($p, $value) === 1) {
                return true;
            }
        }
        return false;
    }
}
