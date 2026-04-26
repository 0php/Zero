<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Str;

trait Transforms
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

    public static function kebab(string $value): string
    {
        $value = self::snake($value);

        return str_replace('_', '-', $value);
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

    public static function slug(string $value, string $separator = '-'): string
    {
        $separator = $separator === '' ? '-' : $separator;

        $value = self::ascii($value, '');
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9\s' . preg_quote($separator, '/') . ']+/u', '', $value);
        $value = preg_replace('/[' . preg_quote($separator, '/') . '\s]+/u', $separator, $value);

        return trim($value, $separator);
    }

    public static function ascii(string $value, string $fallback = '?'): string
    {
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        if ($transliterated === false) {
            return preg_replace('/[^\x00-\x7F]/u', $fallback, $value) ?? $value;
        }

        $clean = preg_replace('/[^\x00-\x7F]/', $fallback, $transliterated);

        return $clean ?? $transliterated;
    }

    public static function transliterate(string $string, string $unknown = '?', bool $strict = false): string
    {
        return self::ascii($string, $unknown);
    }
}
