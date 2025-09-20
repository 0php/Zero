<?php

declare(strict_types=1);

namespace Zero\Lib\Validation\Rules;

use Zero\Lib\Validation\RuleInterface;

final class Confirmed implements RuleInterface
{
    public function name(): string
    {
        return 'confirmed';
    }

    public function passes(string $attribute, mixed $value, array $data): bool
    {
        $confirmationKey = $attribute . '_confirmation';
        $confirmation = $data[$confirmationKey] ?? null;

        return $value === $confirmation;
    }

    public function message(): string
    {
        return 'The :attribute confirmation does not match.';
    }

    public function replacements(string $attribute, mixed $value, array $data): array
    {
        return [];
    }
}

