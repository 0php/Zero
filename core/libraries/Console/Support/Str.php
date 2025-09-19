<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Support;

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
}
