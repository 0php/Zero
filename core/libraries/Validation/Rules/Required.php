<?php

declare(strict_types=1);

namespace Zero\Lib\Validation\Rules;

use Zero\Lib\Validation\RuleInterface;

final class Required implements RuleInterface
{
    public function name(): string
    {
        return 'required';
    }

    public function passes(string $attribute, mixed $value, array $data): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return count($value) > 0;
        }

        return true;
    }

    public function message(): string
    {
        return 'The :attribute field is required.';
    }

    public function replacements(string $attribute, mixed $value, array $data): array
    {
        return [];
    }
}

