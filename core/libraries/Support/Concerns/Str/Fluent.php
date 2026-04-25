<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Str;

use Zero\Lib\Support\Stringable;

trait Fluent
{
    public static function of(string $value): Stringable
    {
        return new Stringable($value);
    }
}
