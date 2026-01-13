<?php

declare(strict_types=1);

namespace Zero\Lib\Validation\Rules;

use Zero\Lib\Validation\RuleInterface;

final class Accepted implements RuleInterface
{
    public function name(): string
    {
        return 'accepted';
    }

    public function passes(string $attribute, mixed $value, array $data): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['yes', 'on', '1', 'true'], true);
        }

        return false;
    }

    public function message(): string
    {
        return 'The :attribute field must be accepted.';
    }

    public function replacements(string $attribute, mixed $value, array $data): array
    {
        return [];
    }
}
