<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Scheduling;

use DateTimeInterface;

final class CronExpression
{
    private const MONTH_NAMES = [
        'jan' => 1,
        'feb' => 2,
        'mar' => 3,
        'apr' => 4,
        'may' => 5,
        'jun' => 6,
        'jul' => 7,
        'aug' => 8,
        'sep' => 9,
        'oct' => 10,
        'nov' => 11,
        'dec' => 12,
    ];

    private const WEEKDAY_NAMES = [
        'sun' => 0,
        'mon' => 1,
        'tue' => 2,
        'wed' => 3,
        'thu' => 4,
        'fri' => 5,
        'sat' => 6,
    ];

    public static function isDue(string $expression, DateTimeInterface $now): bool
    {
        $expression = trim($expression);
        if ($expression === '') {
            return false;
        }

        $parts = preg_split('/\s+/', $expression);
        if ($parts === false || count($parts) !== 5) {
            return false;
        }

        [$minutes, $hours, $dayOfMonth, $month, $dayOfWeek] = $parts;

        return self::fieldMatches($minutes, (int) $now->format('i'), 0, 59)
            && self::fieldMatches($hours, (int) $now->format('H'), 0, 23)
            && self::fieldMatches($month, (int) $now->format('n'), 1, 12, self::MONTH_NAMES)
            && self::fieldMatches($dayOfMonth, (int) $now->format('j'), 1, 31)
            && self::fieldMatches($dayOfWeek, (int) $now->format('w'), 0, 6, self::WEEKDAY_NAMES, true);
    }

    /**
     * @param array<string, int> $names
     */
    private static function fieldMatches(
        string $field,
        int $value,
        int $min,
        int $max,
        array $names = [],
        bool $isDayOfWeek = false
    ): bool {
        $field = strtolower(trim($field));
        if ($field === '') {
            return false;
        }

        if ($field === '?' ) {
            $field = '*';
        }

        foreach ($names as $name => $mapped) {
            $field = preg_replace('/\b' . preg_quote($name, '/') . '\b/', (string) $mapped, $field) ?? $field;
        }

        $segments = explode(',', $field);

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }

            $values = self::expandSegment($segment, $min, $max, $isDayOfWeek);

            if ($values === null) {
                continue;
            }

            if (in_array($value, $values, true)) {
                return true;
            }
        }

        return false;
    }

    private static function expandSegment(string $segment, int $min, int $max, bool $isDayOfWeek): ?array
    {
        if ($segment === '*') {
            return range($min, $max);
        }

        if (str_contains($segment, '/')) {
            [$base, $step] = explode('/', $segment, 2);
            $step = (int) $step;
            if ($step <= 0) {
                return null;
            }

            $baseValues = self::expandSegment($base === '' ? '*' : $base, $min, $max, $isDayOfWeek);
            if ($baseValues === null) {
                return null;
            }

            return array_values(array_filter($baseValues, static fn (int $candidate): bool => ($candidate - $min) % $step === 0));
        }

        if (str_contains($segment, '-')) {
            [$start, $end] = explode('-', $segment, 2);
            $startNumber = self::normalizeNumber($start, $min, $max, $isDayOfWeek);
            $endNumber = self::normalizeNumber($end, $min, $max, $isDayOfWeek);

            if ($startNumber === null || $endNumber === null) {
                return null;
            }

            if ($startNumber <= $endNumber) {
                return range($startNumber, $endNumber);
            }

            return array_merge(range($startNumber, $max), range($min, $endNumber));
        }

        $number = self::normalizeNumber($segment, $min, $max, $isDayOfWeek);

        if ($number === null) {
            return null;
        }

        return [$number];
    }

    private static function normalizeNumber(string $value, int $min, int $max, bool $isDayOfWeek): ?int
    {
        if ($value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $number = (int) $value;

        if ($isDayOfWeek && $number === 7) {
            $number = 0;
        }

        if ($number < $min || $number > $max) {
            return null;
        }

        return $number;
    }
}
