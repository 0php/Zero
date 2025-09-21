<?php

declare(strict_types=1);

namespace Zero\Lib\Validation\Rules;

use Zero\Lib\Validation\RuleInterface;

final class Number implements RuleInterface
{
    public function name(): string
    {
        return 'number';
    }

    public function passes(string $attribute, mixed $value, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_int($value) || is_float($value)) {
            return true;
        }

        if (is_string($value)) {
            $normalized = trim($value);

            if ($normalized === '') {
                return false;
            }

            return is_numeric($normalized);
        }

        return false;
    }

    public function message(): string
    {
        return 'The :attribute must be a number.';
    }

    public function replacements(string $attribute, mixed $value, array $data): array
    {
        return [];
    }
}
