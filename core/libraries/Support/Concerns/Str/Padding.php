<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Str;

trait Padding
{
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
