<?php

declare(strict_types=1);

namespace Zero\Lib\Validation\Rules;

use Zero\Lib\DB\DBML;
use Zero\Lib\Validation\RuleInterface;

final class Exists implements RuleInterface
{
    public function __construct(
        private string $table,
        private ?string $column = null
    ) {
    }

    public function name(): string
    {
        return 'exists';
    }

    public function passes(string $attribute, mixed $value, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_array($value)) {
            foreach ($value as $element) {
                if (!$this->valueExists($attribute, $element)) {
                    return false;
                }
            }

            return true;
        }

        return $this->valueExists($attribute, $value);
    }

    public function message(): string
    {
        return 'The selected :attribute is invalid.';
    }

    public function replacements(string $attribute, mixed $value, array $data): array
    {
        return [
            'table' => $this->table,
            'column' => $this->column ?? $attribute,
        ];
    }

    private function valueExists(string $attribute, mixed $value): bool
    {
        $column = $this->column ?? $attribute;

        return DBML::table($this->table)
            ->where($column, '=', $value)
            ->exists();
    }
}

