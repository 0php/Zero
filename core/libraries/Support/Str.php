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

    public static function slug(string $value, string $separator = '-'): string
    {
        $separator = $separator === '' ? '-' : $separator;

        $value = self::ascii($value, '');
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9\s' . preg_quote($separator, '/') . ']+/u', '', $value);
        $value = preg_replace('/[' . preg_quote($separator, '/') . '\s]+/u', $separator, $value);

        return trim($value, $separator);
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

    public static function random(int $length = 16, ?string $alphabet = null): string
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('Length must be greater than zero.');
        }

        $alphabet ??= 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $alphabetLength = strlen($alphabet);

        if ($alphabetLength === 0) {
            throw new \InvalidArgumentException('Alphabet must not be empty.');
        }

        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $index = random_int(0, $alphabetLength - 1);
            $result .= $alphabet[$index];
        }

        return $result;
    }

    public static function length(string $value, ?string $encoding = null): int
    {
        return $encoding === null
            ? mb_strlen($value)
            : mb_strlen($value, $encoding);
    }

    public static function substr(string $value, int $start, ?int $length = null, ?string $encoding = null): string
    {
        return $encoding === null
            ? ($length === null ? mb_substr($value, $start) : mb_substr($value, $start, $length))
            : ($length === null ? mb_substr($value, $start, null, $encoding) : mb_substr($value, $start, $length, $encoding));
    }

    public static function words(string $value, int $words, string $end = '...'): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $segments = preg_split('/\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY);

        if ($segments === false || count($segments) <= $words) {
            return $value;
        }

        $limited = array_slice($segments, 0, max($words, 0));

        return implode(' ', $limited) . $end;
    }

    public static function limitWords(string $value, int $words, string $end = '...'): string
    {
        return self::words($value, $words, $end);
    }

    public static function after(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = mb_strpos($subject, $search);

        if ($position === false) {
            return $subject;
        }

        return mb_substr($subject, $position + mb_strlen($search));
    }

    public static function before(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = mb_strpos($subject, $search);

        if ($position === false) {
            return $subject;
        }

        return mb_substr($subject, 0, $position);
    }

    public static function between(string $subject, string $from, string $to): string
    {
        if ($from === '' || $to === '') {
            return '';
        }

        $result = self::after($subject, $from);
        $result = self::before($result, $to);

        return $result;
    }

    public static function replaceFirst(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = mb_strpos($subject, $search);

        if ($position === false) {
            return $subject;
        }

        return mb_substr($subject, 0, $position)
            . $replace
            . mb_substr($subject, $position + mb_strlen($search));
    }

    public static function replaceLast(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = mb_strrpos($subject, $search);

        if ($position === false) {
            return $subject;
        }

        return mb_substr($subject, 0, $position)
            . $replace
            . mb_substr($subject, $position + mb_strlen($search));
    }

    public static function swap(array $map, string $subject): string
    {
        return strtr($subject, $map);
    }

    public static function containsAll(string $haystack, iterable $needles): bool
    {
        foreach ($needles as $needle) {
            if (!self::contains($haystack, (string) $needle)) {
                return false;
            }
        }

        return true;
    }

    public static function containsAny(string $haystack, iterable $needles): bool
    {
        foreach ($needles as $needle) {
            if (self::contains($haystack, (string) $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function startsWithAny(string $haystack, iterable $needles): bool
    {
        foreach ($needles as $needle) {
            if (self::startsWith($haystack, (string) $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function endsWithAny(string $haystack, iterable $needles): bool
    {
        foreach ($needles as $needle) {
            if (self::endsWith($haystack, (string) $needle)) {
                return true;
            }
        }

        return false;
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

    public static function padLeft(string $value, int $length, string $pad = ' '): string
    {
        return self::pad($value, $length, $pad, 'left');
    }

    public static function padRight(string $value, int $length, string $pad = ' '): string
    {
        return self::pad($value, $length, $pad, 'right');
    }

    public static function padBoth(string $value, int $length, string $pad = ' '): string
    {
        return self::pad($value, $length, $pad, 'both');
    }

    public static function repeat(string $value, int $times): string
    {
        if ($times < 0) {
            throw new \InvalidArgumentException('Times must be zero or greater.');
        }

        return str_repeat($value, $times);
    }

    public static function of(string $value): Stringable
    {
        return new Stringable($value);
    }

    private static function pad(string $value, int $length, string $pad, string $side): string
    {
        if ($length <= 0) {
            return '';
        }

        $valueLength = mb_strlen($value);

        if ($valueLength >= $length) {
            return $value;
        }

        $padLength = $length - $valueLength;

        $pad = $pad === '' ? ' ' : $pad;

        $left = 0;
        $right = 0;

        switch ($side) {
            case 'left':
                $left = $padLength;
                break;
            case 'right':
                $right = $padLength;
                break;
            default:
                $left = intdiv($padLength, 2);
                $right = $padLength - $left;
        }

        return self::repeatToLength($pad, $left) . $value . self::repeatToLength($pad, $right);
    }

    private static function repeatToLength(string $pad, int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        $padLength = mb_strlen($pad);

        if ($padLength === 0) {
            return str_repeat(' ', $length);
        }

        $repeats = (int) ceil($length / $padLength);
        $result = str_repeat($pad, $repeats);

        return mb_substr($result, 0, $length);
    }
}
