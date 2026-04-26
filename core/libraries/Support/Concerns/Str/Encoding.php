<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Str;

trait Encoding
{
    public static function toBase64(string $value): string
    {
        return base64_encode($value);
    }

    public static function fromBase64(string $value, bool $strict = false): string
    {
        $decoded = base64_decode($value, $strict);
        return $decoded === false ? '' : $decoded;
    }
}
