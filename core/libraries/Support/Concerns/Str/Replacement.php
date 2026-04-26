<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Str;

trait Replacement
{
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

    public static function replace(string|array $search, string|array $replace, string|array $subject, bool $caseSensitive = true): string|array
    {
        return $caseSensitive ? str_replace($search, $replace, $subject) : str_ireplace($search, $replace, $subject);
    }

    public static function replaceArray(string $search, array $replace, string $subject): string
    {
        $segments = explode($search, $subject);
        $result = array_shift($segments);
        foreach ($segments as $segment) {
            $result .= (array_shift($replace) ?? $search) . $segment;
        }
        return $result;
    }

    public static function replaceStart(string $search, string $replace, string $subject): string
    {
        if ($search !== '' && str_starts_with($subject, $search)) {
            return $replace . substr($subject, strlen($search));
        }
        return $subject;
    }

    public static function replaceEnd(string $search, string $replace, string $subject): string
    {
        if ($search !== '' && str_ends_with($subject, $search)) {
            return substr($subject, 0, -strlen($search)) . $replace;
        }
        return $subject;
    }

    public static function replaceMatches(string $pattern, string|callable $replace, string $subject, int $limit = -1): string
    {
        if (is_callable($replace)) {
            return (string) preg_replace_callback($pattern, $replace, $subject, $limit);
        }
        return (string) preg_replace($pattern, $replace, $subject, $limit);
    }

    public static function swap(array $map, string $subject): string
    {
        return strtr($subject, $map);
    }

    public static function remove(string|iterable $search, string $subject, bool $caseSensitive = true): string
    {
        $search = is_iterable($search) ? (array) $search : [$search];
        return $caseSensitive
            ? str_replace($search, '', $subject)
            : str_ireplace($search, '', $subject);
    }

    public static function substrReplace(string $string, string $replace, int $offset = 0, ?int $length = null): string
    {
        return $length === null ? substr_replace($string, $replace, $offset) : substr_replace($string, $replace, $offset, $length);
    }
}
