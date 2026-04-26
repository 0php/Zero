<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Str;

trait Pluralization
{
    public static function plural(string $value, int|array|\Countable $count = 2): string
    {
        $n = is_int($count) ? $count : count($count);
        if ($n === 1) {
            return $value;
        }
        $lower = strtolower($value);
        if (preg_match('/(s|x|z|ch|sh)$/', $lower)) {
            return $value . 'es';
        }
        if (preg_match('/[^aeiou]y$/', $lower)) {
            return substr($value, 0, -1) . 'ies';
        }
        return $value . 's';
    }

    public static function singular(string $value): string
    {
        $lower = strtolower($value);
        if (preg_match('/ies$/', $lower)) {
            return substr($value, 0, -3) . 'y';
        }
        if (preg_match('/(s|x|z|ch|sh)es$/', $lower)) {
            return substr($value, 0, -2);
        }
        if (preg_match('/s$/', $lower) && ! preg_match('/(ss|us|is)$/', $lower)) {
            return substr($value, 0, -1);
        }
        return $value;
    }

    public static function pluralStudly(string $value, int|array|\Countable $count = 2): string
    {
        $parts = self::ucsplit($value);
        $last = array_pop($parts);
        return implode('', $parts) . self::plural($last ?? '', $count);
    }
}
