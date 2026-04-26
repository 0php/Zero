<?php

declare(strict_types=1);

namespace Zero\Lib\Support;

use NumberFormatter;

final class Number
{
    public static function format(int|float $number, int $precision = 0, ?int $maxPrecision = null, ?string $locale = null): string
    {
        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale ?? 'en', NumberFormatter::DECIMAL);
            if ($maxPrecision !== null) {
                $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $maxPrecision);
            }
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $precision);
            return $formatter->format($number);
        }
        return number_format($number, $maxPrecision ?? $precision, '.', ',');
    }

    public static function spell(int|float $number, ?string $locale = null): string
    {
        if (! class_exists(NumberFormatter::class)) {
            return (string) $number;
        }
        $formatter = new NumberFormatter($locale ?? 'en', NumberFormatter::SPELLOUT);
        return $formatter->format($number);
    }

    public static function ordinal(int $number, ?string $locale = null): string
    {
        if (! class_exists(NumberFormatter::class)) {
            $suffix = match (true) {
                $number % 100 >= 11 && $number % 100 <= 13 => 'th',
                $number % 10 === 1 => 'st',
                $number % 10 === 2 => 'nd',
                $number % 10 === 3 => 'rd',
                default => 'th',
            };
            return $number . $suffix;
        }
        $formatter = new NumberFormatter($locale ?? 'en', NumberFormatter::ORDINAL);
        return $formatter->format($number);
    }

    public static function percentage(int|float $number, int $precision = 0, ?int $maxPrecision = null, ?string $locale = null): string
    {
        return self::format($number, $precision, $maxPrecision, $locale) . '%';
    }

    public static function currency(int|float $number, string $currency = 'USD', ?string $locale = null): string
    {
        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale ?? 'en', NumberFormatter::CURRENCY);
            return $formatter->formatCurrency($number, $currency);
        }
        return $currency . ' ' . number_format($number, 2, '.', ',');
    }

    public static function fileSize(int|float $bytes, int $precision = 0, ?int $maxPrecision = null): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $bytes = max($bytes, 0);
        $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);
        $value = $bytes / (1024 ** $power);
        return self::format($value, $precision, $maxPrecision) . ' ' . $units[$power];
    }

    public static function forHumans(int|float $number, int $precision = 0, ?int $maxPrecision = null, bool $abbreviate = false): string
    {
        $units = $abbreviate
            ? [3 => 'K', 6 => 'M', 9 => 'B', 12 => 'T', 15 => 'Q']
            : [3 => ' thousand', 6 => ' million', 9 => ' billion', 12 => ' trillion', 15 => ' quadrillion'];

        $absolute = abs($number);
        if ($absolute < 1000) {
            return self::format($number, $precision, $maxPrecision);
        }

        $unitKey = null;
        foreach (array_keys($units) as $exp) {
            if ($absolute >= 10 ** $exp) {
                $unitKey = $exp;
            }
        }
        if ($unitKey === null) {
            return self::format($number, $precision, $maxPrecision);
        }
        $value = $number / (10 ** $unitKey);
        return self::format($value, $precision, $maxPrecision) . $units[$unitKey];
    }

    public static function abbreviate(int|float $number, int $precision = 0, ?int $maxPrecision = null): string
    {
        return self::forHumans($number, $precision, $maxPrecision, true);
    }

    public static function pairs(int|float $to, int|float $by, int|float $offset = 1): array
    {
        $output = [];
        for ($lower = $offset; $lower < $to; $lower += $by) {
            $upper = $lower + $by - 1;
            if ($upper > $to) $upper = $to;
            $output[] = [$lower, $upper];
        }
        return $output;
    }

    public static function trim(int|float $number): int|float
    {
        if (is_int($number)) return $number;
        $string = rtrim(rtrim(sprintf('%f', $number), '0'), '.');
        return $string === '' ? 0 : (str_contains($string, '.') ? (float) $string : (int) $string);
    }

    public static function clamp(int|float $number, int|float $min, int|float $max): int|float
    {
        return min(max($number, $min), $max);
    }
}
