<?php

declare(strict_types=1);

namespace Zero\Lib\Validation\Rules;

use Zero\Lib\Validation\RuleInterface;

final class StringRule implements RuleInterface
{
    public function name(): string
    {
        return 'string';
    }

    public function passes(string $attribute, mixed $value, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        return is_string($value);
    }

    public function message(): string
    {
        return 'The :attribute must be a string.';
    }

    public function replacements(string $attribute, mixed $value, array $data): array
    {
        return [];
    }
}

