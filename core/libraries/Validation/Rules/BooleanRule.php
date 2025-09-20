<?php

declare(strict_types=1);

namespace Zero\Lib\Validation\Rules;

use Zero\Lib\Validation\RuleInterface;

final class BooleanRule implements RuleInterface
{
    public function name(): string
    {
        return 'boolean';
    }

    public function passes(string $attribute, mixed $value, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_bool($value)) {
            return true;
        }

        if (is_string($value)) {
            $value = strtolower($value);

            return in_array($value, ['1', '0', 'true', 'false', 'on', 'off'], true);
        }

        if (is_numeric($value)) {
            return in_array((int) $value, [0, 1], true);
        }

        return false;
    }

    public function message(): string
    {
        return 'The :attribute field must be true or false.';
    }

    public function replacements(string $attribute, mixed $value, array $data): array
    {
        return [];
    }
}

