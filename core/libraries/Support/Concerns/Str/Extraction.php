<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Str;

trait Extraction
{
    public static function limit(string $value, int $limit, string $end = '...'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }
        return rtrim(mb_substr($value, 0, $limit)) . $end;
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

    public static function substr(string $value, int $start, ?int $length = null, ?string $encoding = null): string
    {
        return $encoding === null
            ? ($length === null ? mb_substr($value, $start) : mb_substr($value, $start, $length))
            : ($length === null ? mb_substr($value, $start, null, $encoding) : mb_substr($value, $start, $length, $encoding));
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

    public static function afterLast(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }
        $position = strrpos($subject, $search);
        return $position === false ? $subject : substr($subject, $position + strlen($search));
    }

    public static function beforeLast(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }
        $position = strrpos($subject, $search);
        return $position === false ? $subject : substr($subject, 0, $position);
    }

    public static function betweenFirst(string $subject, string $from, string $to): string
    {
        if ($from === '' || $to === '') {
            return $subject;
        }
        return self::before(self::after($subject, $from), $to);
    }

    public static function charAt(string $subject, int $index): string|false
    {
        $length = mb_strlen($subject);
        if ($index < 0) {
            $index += $length;
        }
        if ($index < 0 || $index >= $length) {
            return false;
        }
        return mb_substr($subject, $index, 1);
    }

    public static function take(string $string, int $limit): string
    {
        if ($limit < 0) {
            return mb_substr($string, $limit);
        }
        return mb_substr($string, 0, $limit);
    }

    public static function excerpt(string $text, string $phrase = '', array $options = []): ?string
    {
        $radius = $options['radius'] ?? 100;
        $omission = $options['omission'] ?? '...';

        if ($phrase === '') {
            return self::limit($text, $radius * 2, $omission);
        }

        $position = mb_stripos($text, $phrase);
        if ($position === false) {
            return null;
        }

        $startPos = max($position - $radius, 0);
        $endPos = min($position + mb_strlen($phrase) + $radius, mb_strlen($text));

        $prefix = $startPos > 0 ? $omission : '';
        $suffix = $endPos < mb_strlen($text) ? $omission : '';

        return $prefix . trim(mb_substr($text, $startPos, $endPos - $startPos)) . $suffix;
    }

    public static function match(string $pattern, string $subject): string
    {
        preg_match($pattern, $subject, $matches);
        if ($matches === []) {
            return '';
        }
        return $matches[1] ?? $matches[0];
    }

    /**
     * @return array<int, string>
     */
    public static function matchAll(string $pattern, string $subject): array
    {
        preg_match_all($pattern, $subject, $matches);
        if (empty($matches[0])) {
            return [];
        }
        return $matches[1] ?? $matches[0];
    }
}
