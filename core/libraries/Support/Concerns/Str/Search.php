<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Str;

trait Search
{
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

    public static function containsAll(string $haystack, iterable $needles): bool
    {
        foreach ($needles as $needle) {
            if (! self::contains($haystack, (string) $needle)) {
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

    public static function doesntContain(string $haystack, string|iterable $needles, bool $ignoreCase = false): bool
    {
        return ! self::containsAny($haystack, is_iterable($needles) ? $needles : [$needles]);
    }

    public static function doesntStartWith(string $haystack, string|iterable $needles): bool
    {
        return ! self::startsWithAny($haystack, is_iterable($needles) ? $needles : [$needles]);
    }

    public static function doesntEndWith(string $haystack, string|iterable $needles): bool
    {
        return ! self::endsWithAny($haystack, is_iterable($needles) ? $needles : [$needles]);
    }

    public static function position(string $haystack, string $needle, int $offset = 0, ?string $encoding = null): int|false
    {
        return mb_strpos($haystack, $needle, $offset, $encoding ?? 'UTF-8');
    }

    public static function substrCount(string $haystack, string $needle, int $offset = 0, ?int $length = null): int
    {
        if ($length === null) {
            return substr_count($haystack, $needle, $offset);
        }
        return substr_count($haystack, $needle, $offset, $length);
    }
}
