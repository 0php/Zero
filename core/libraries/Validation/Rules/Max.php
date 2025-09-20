<?php

declare(strict_types=1);

namespace Zero\Lib\Validation\Rules;

use Zero\Lib\Validation\RuleInterface;

final class Max implements RuleInterface
{
    public function __construct(private int|float $max)
    {
    }

    public function name(): string
    {
        return 'max';
    }

    public function passes(string $attribute, mixed $value, array $data): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return mb_strlen($value) <= $this->max;
        }

        if (is_array($value)) {
            return count($value) <= $this->max;
        }

        if (is_numeric($value)) {
            return (float) $value <= $this->max;
        }

        return false;
    }

    public function message(): string
    {
        return 'The :attribute may not be greater than :max.';
    }

    public function replacements(string $attribute, mixed $value, array $data): array
    {
        return ['max' => $this->formatNumber($this->max)];
    }

    private function formatNumber(int|float $value): string
    {
        if (is_int($value) || fmod($value, 1.0) === 0.0) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
    }
}
