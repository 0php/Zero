<?php

declare(strict_types=1);

namespace Zero\Lib\Validation\Rules;

use Zero\Lib\Filesystem\File;
use Zero\Lib\Validation\RuleInterface;

final class Max implements RuleInterface
{
    private string $context = 'default';

    public function __construct(private int|float $max)
    {
    }

    public function name(): string
    {
        return 'max';
    }

    public function passes(string $attribute, mixed $value, array $data): bool
    {
        $this->context = 'default';

        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            $this->context = 'string';
            return mb_strlen($value) <= $this->max;
        }

        if (is_array($value)) {
            $this->context = 'array';
            return count($value) <= $this->max;
        }

        if (is_numeric($value)) {
            $this->context = 'numeric';
            return (float) $value <= $this->max;
        }

        if ($value instanceof File) {
            $this->context = 'file';

            return $this->fileSizeInKilobytes($value) <= $this->max;
        }

        return false;
    }

    public function message(): string
    {
        if ($this->context === 'file') {
            return 'The :attribute may not be greater than :max kilobytes.';
        }

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

    private function fileSizeInKilobytes(File $file): float
    {
        return $file->getSize() / 1024;
    }
}
