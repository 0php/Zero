<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Str;

trait Composition
{
    public static function start(string $value, string $prefix): string
    {
        $quoted = preg_quote($prefix, '/');
        return $prefix . preg_replace('/^(?:' . $quoted . ')+/u', '', $value);
    }

    public static function finish(string $value, string $cap): string
    {
        $quoted = preg_quote($cap, '/');
        return preg_replace('/(?:' . $quoted . ')+$/u', '', $value) . $cap;
    }

    public static function ensureSuffix(string $value, string $suffix): string
    {
        return str_ends_with($value, $suffix) ? $value : $value . $suffix;
    }

    public static function wrap(string $value, string $before, ?string $after = null): string
    {
        return $before . $value . ($after ?? $before);
    }

    public static function unwrap(string $value, string $before, ?string $after = null): string
    {
        $after ??= $before;
        if (str_starts_with($value, $before)) {
            $value = substr($value, strlen($before));
        }
        if (str_ends_with($value, $after)) {
            $value = substr($value, 0, -strlen($after));
        }
        return $value;
    }

    public static function reverse(string $value): string
    {
        return implode('', array_reverse(mb_str_split($value)));
    }

    public static function squish(string $value): string
    {
        return trim((string) preg_replace('/\s+|\x{3164}|\x{1160}/u', ' ', $value));
    }

    public static function deduplicate(string $value, string $character = ' '): string
    {
        $quoted = preg_quote($character, '/');
        return (string) preg_replace('/(' . $quoted . ')+/u', $character, $value);
    }

    public static function chopStart(string $subject, string|array $needle): string
    {
        foreach ((array) $needle as $value) {
            if ($value !== '' && str_starts_with($subject, $value)) {
                return substr($subject, strlen($value));
            }
        }
        return $subject;
    }

    public static function chopEnd(string $subject, string|array $needle): string
    {
        foreach ((array) $needle as $value) {
            if ($value !== '' && str_ends_with($subject, $value)) {
                return substr($subject, 0, -strlen($value));
            }
        }
        return $subject;
    }

    public static function trim(string $value, ?string $charlist = null): string
    {
        return $charlist === null ? trim($value) : trim($value, $charlist);
    }

    public static function ltrim(string $value, ?string $charlist = null): string
    {
        return $charlist === null ? ltrim($value) : ltrim($value, $charlist);
    }

    public static function rtrim(string $value, ?string $charlist = null): string
    {
        return $charlist === null ? rtrim($value) : rtrim($value, $charlist);
    }

    public static function headline(string $value): string
    {
        $parts = explode(' ', $value);
        $parts = count($parts) > 1
            ? array_map(static fn($part) => self::title($part), $parts)
            : array_map(static fn($part) => self::title($part), self::ucsplit(implode('_', $parts)));

        $collapsed = self::replace(['-', '_', ' '], '_', implode('_', $parts));
        return implode(' ', array_filter(explode('_', $collapsed)));
    }

    public static function apa(string $value): string
    {
        if (trim($value) === '') {
            return $value;
        }

        $minor = ['and', 'as', 'but', 'for', 'if', 'nor', 'or', 'so', 'yet', 'a', 'an', 'the', 'at', 'by', 'for', 'in', 'of', 'off', 'on', 'per', 'to', 'up', 'via'];
        $words = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY);

        $result = [];
        $count = count($words);
        foreach ($words as $i => $word) {
            $lower = mb_strtolower($word);
            if ($i !== 0 && $i !== $count - 1 && in_array($lower, $minor, true)) {
                $result[] = $lower;
            } else {
                $result[] = mb_strtoupper(mb_substr($word, 0, 1)) . mb_substr($word, 1);
            }
        }

        return implode(' ', $result);
    }

    public static function initials(string $value, string $glue = ''): string
    {
        $words = preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
        return implode($glue, array_map(static fn($word) => mb_strtoupper(mb_substr($word, 0, 1)), $words ?: []));
    }

    public static function mask(string $value, string $character, int $index, ?int $length = null, string $encoding = 'UTF-8'): string
    {
        if ($character === '') {
            return $value;
        }
        $valueLength = mb_strlen($value, $encoding);
        $start = $index;
        if ($start < 0) {
            $start = max(0, $valueLength + $start);
        }
        $segmentLength = $length ?? $valueLength;
        if ($segmentLength < 0) {
            $segmentLength = max(0, $valueLength - $start + $segmentLength);
        }
        $segmentLength = min($segmentLength, $valueLength - $start);

        $start_str = mb_substr($value, 0, $start, $encoding);
        $end_str = mb_substr($value, $start + $segmentLength, null, $encoding);
        return $start_str . str_repeat(mb_substr($character, 0, 1, $encoding), $segmentLength) . $end_str;
    }
}
