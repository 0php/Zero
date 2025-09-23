<?php

declare(strict_types=1);

namespace Zero\Lib\Support;

final class Str
{
    
    public static function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = ucwords($value);

        return str_replace(' ', '', $value);
    }

    public static function snake(string $value): string
    {
        $value = preg_replace('/([a-z])([A-Z])/', '$1_$2', $value);
        $value = str_replace(['-', ' '], '_', $value);
        $value = strtolower((string) $value);
        $value = preg_replace('/_+/', '_', $value);

        return trim((string) $value, '_');
    }

    public static function ensureSuffix(string $value, string $suffix): string
    {
        return str_ends_with($value, $suffix) ? $value : $value . $suffix;
    }

    public static function kebab(string $value): string
    {
        $value = self::snake($value);

        return str_replace('_', '-', $value);
    }

    public static function slug(string $value): string
    {
        return self::kebab($value);
    }

    public static function camel(string $value): string
    {
        $studly = self::studly($value);

        return lcfirst($studly);
    }

    public static function title(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', trim($value));

        return ucwords(strtolower($value));
    }

    public static function upper(string $value): string
    {
        return strtoupper($value);
    }

    public static function lower(string $value): string
    {
        return strtolower($value);
    }

    public static function contains(string $haystack, string $needle): bool
    {
        return $needle === '' || str_contains($haystack, $needle);
    }

    public static function startsWith(string $haystack, string $needle): bool
    {
        return str_starts_with($haystack, $needle);
    }

    public static function endsWith(string $haystack, string $needle): bool
    {
        return str_ends_with($haystack, $needle);
    }

    public static function limit(string $value, int $limit, string $end = '...'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit)) . $end;
    }

    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        $hex = bin2hex($data);
        return vsprintf('%s-%s-%s-%s-%s', [
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        ]);
    }
}
