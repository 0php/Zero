<?php

declare(strict_types=1);

namespace Zero\Lib\Validation\Rules;

use Zero\Lib\Validation\RuleInterface;

final class Email implements RuleInterface
{
    public function name(): string
    {
        return 'email';
    }

    public function passes(string $attribute, mixed $value, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function message(): string
    {
        return 'The :attribute must be a valid email address.';
    }

    public function replacements(string $attribute, mixed $value, array $data): array
    {
        return [];
    }
}

