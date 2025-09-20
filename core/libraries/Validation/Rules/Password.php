<?php

declare(strict_types=1);

namespace Zero\Lib\Validation\Rules;

use Zero\Lib\Validation\RuleInterface;

final class Password implements RuleInterface
{
    public function __construct(private array $requirements = [])
    {
    }

    public function name(): string
    {
        return 'password';
    }

    public function passes(string $attribute, mixed $value, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        foreach ($this->requirements as $requirement) {
            $method = 'validate' . ucfirst($requirement);

            if (method_exists($this, $method) && !$this->{$method}($value)) {
                return false;
            }
        }

        return true;
    }

    public function message(): string
    {
        if (in_array('letters', $this->requirements, true) && in_array('numbers', $this->requirements, true) && in_array('symbols', $this->requirements, true)) {
            return 'The :attribute must contain letters, numbers, and symbols.';
        }

        if (in_array('letters', $this->requirements, true) && in_array('numbers', $this->requirements, true)) {
            return 'The :attribute must contain letters and numbers.';
        }

        if (in_array('symbols', $this->requirements, true) && in_array('numbers', $this->requirements, true)) {
            return 'The :attribute must contain numbers and symbols.';
        }

        if (in_array('letters', $this->requirements, true)) {
            return 'The :attribute must contain both uppercase and lowercase letters.';
        }

        if (in_array('numbers', $this->requirements, true)) {
            return 'The :attribute must contain at least one number.';
        }

        if (in_array('symbols', $this->requirements, true)) {
            return 'The :attribute must contain at least one symbol.';
        }

        return 'The :attribute format is invalid.';
    }

    public function replacements(string $attribute, mixed $value, array $data): array
    {
        return [];
    }

    private function validateLetters(string $value): bool
    {
        return preg_match('/[a-z]/', $value) === 1 && preg_match('/[A-Z]/', $value) === 1;
    }

    private function validateNumbers(string $value): bool
    {
        return preg_match('/\d/', $value) === 1;
    }

    private function validateSymbols(string $value): bool
    {
        return preg_match('/[^\w]/', $value) === 1;
    }
}

