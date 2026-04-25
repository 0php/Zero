<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Str;

trait Casing
{
    public static function lcfirst(string $value): string
    {
        return mb_strtolower(mb_substr($value, 0, 1)) . mb_substr($value, 1);
    }

    public static function ucfirst(string $value): string
    {
        return mb_strtoupper(mb_substr($value, 0, 1)) . mb_substr($value, 1);
    }

    public static function ucwords(string $value, string $delimiters = " \t\r\n\f\v"): string
    {
        return ucwords($value, $delimiters);
    }

    /**
     * @return array<int, string>
     */
    public static function ucsplit(string $value): array
    {
        return preg_split('/(?=\p{Lu})/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    public static function length(string $value, ?string $encoding = null): int
    {
        return $encoding === null
            ? mb_strlen($value)
            : mb_strlen($value, $encoding);
    }

    public static function wordCount(string $string, ?string $characters = null): int
    {
        return str_word_count($string, 0, $characters ?? '');
    }

    public static function wordWrap(string $string, int $characters = 75, string $break = "\n", bool $cutLongWords = false): string
    {
        return wordwrap($string, $characters, $break, $cutLongWords);
    }
}
