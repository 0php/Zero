<?php

declare(strict_types=1);

namespace Zero\Lib\Validation\Rules;

use Zero\Lib\Validation\RuleInterface;

final class ArrayRule implements RuleInterface
{
    public function name(): string
    {
        return 'array';
    }

    public function passes(string $attribute, mixed $value, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        return is_array($value);
    }

    public function message(): string
    {
        return 'The :attribute must be an array.';
    }

    public function replacements(string $attribute, mixed $value, array $data): array
    {
        return [];
    }
}

