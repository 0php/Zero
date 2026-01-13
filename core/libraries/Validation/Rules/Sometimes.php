<?php

declare(strict_types=1);

namespace Zero\Lib\Validation\Rules;

use Zero\Lib\Validation\RuleInterface;

final class Sometimes implements RuleInterface
{
    public function name(): string
    {
        return 'sometimes';
    }

    public function passes(string $attribute, mixed $value, array $data): bool
    {
        return true;
    }

    public function message(): string
    {
        return '';
    }

    public function replacements(string $attribute, mixed $value, array $data): array
    {
        return [];
    }
}
