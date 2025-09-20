<?php

declare(strict_types=1);

namespace Zero\Lib\Validation\Rules;

use Zero\Lib\DB\DBML;
use Zero\Lib\Validation\RuleInterface;

final class Unique implements RuleInterface
{
    public function __construct(
        private string $table,
        private ?string $column = null,
        private mixed $ignoreValue = null,
        private ?string $idColumn = null
    ) {
    }

    public function name(): string
    {
        return 'unique';
    }

    public function passes(string $attribute, mixed $value, array $data): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_array($value)) {
            foreach ($value as $element) {
                if (!$this->valueIsUnique($attribute, $element)) {
                    return false;
                }
            }

            return true;
        }

        return $this->valueIsUnique($attribute, $value);
    }

    public function message(): string
    {
        return 'The :attribute has already been taken.';
    }

    public function replacements(string $attribute, mixed $value, array $data): array
    {
        return [
            'table' => $this->table,
            'column' => $this->column ?? $attribute,
        ];
    }

    private function valueIsUnique(string $attribute, mixed $value): bool
    {
        $column = $this->column ?? $attribute;
        $query = DBML::table($this->table)
            ->where($column, '=', $value);

        if ($this->ignoreValue !== null) {
            $idColumn = $this->idColumn ?? 'id';
            $query->where($idColumn, '!=', $this->ignoreValue);
        }

        return !$query->exists();
    }
}

