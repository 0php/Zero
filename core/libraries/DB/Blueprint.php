<?php

declare(strict_types=1);

namespace Zero\Lib\DB;

use InvalidArgumentException;

/**
 * Fluent schema definition object used by {@see Schema} to compose SQL.
 */
class Blueprint
{
    /** @var string[] */
    protected array $columns = [];

    /** @var string[] */
    protected array $operations = [];

    /** @var string[] */
    protected array $indexes = [];

    public function __construct(protected string $table, protected string $action = 'table')
    {
        if (!in_array($this->action, ['create', 'table'], true)) {
            throw new InvalidArgumentException('Invalid blueprint action.');
        }
    }

    /** Add an auto-incrementing primary key column. */
    public function id(string $column = 'id'): self
    {
        return $this->column("`{$column}` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY");
    }

    public function increments(string $column): self
    {
        return $this->id($column);
    }

    /** Add an integer column. */
    public function integer(string $column, bool $unsigned = false, bool $nullable = false, mixed $default = null): self
    {
        return $this->column($this->compileColumn($column, $unsigned ? 'INT UNSIGNED' : 'INT', $nullable, $default));
    }

    /** Add a big integer column. */
    public function bigInteger(string $column, bool $unsigned = false, bool $nullable = false, mixed $default = null): self
    {
        return $this->column($this->compileColumn($column, $unsigned ? 'BIGINT UNSIGNED' : 'BIGINT', $nullable, $default));
    }

    /** Add a string/varchar column. */
    public function string(string $column, int $length = 255, bool $nullable = false, mixed $default = null): self
    {
        return $this->column($this->compileColumn($column, sprintf('VARCHAR(%d)', $length), $nullable, $default));
    }

    /** Add a text column. */
    public function text(string $column, bool $nullable = true): self
    {
        $definition = sprintf('`%s` TEXT', $column);
        $definition .= $nullable ? ' NULL' : ' NOT NULL';

        return $this->column($definition);
    }

    /** Add a boolean column. */
    public function boolean(string $column, bool $nullable = false, bool $default = false): self
    {
        return $this->column($this->compileColumn($column, 'TINYINT(1)', $nullable, $default ? 1 : 0));
    }

    /** Add a timestamp column optionally allowing null/default values. */
    public function timestamp(string $column, bool $nullable = false, mixed $default = null): self
    {
        return $this->column($this->compileColumn($column, 'TIMESTAMP', $nullable, $default));
    }

    /** Add a DATETIME column definition. */
    public function datetime(string $column, bool $nullable = false, mixed $default = null): self
    {
        return $this->column($this->compileColumn($column, 'DATETIME', $nullable, $default));
    }

    /** Add a generic index for the given columns. */
    public function index(string|array $columns, ?string $name = null): self
    {
        return $this->addIndex('index', (array) $columns, $name);
    }

    /** Add a unique index constraint. */
    public function unique(string|array $columns, ?string $name = null): self
    {
        return $this->addIndex('unique', (array) $columns, $name);
    }

    /** Mark the given columns as the table primary key. */
    public function primary(string|array $columns, ?string $name = null): self
    {
        return $this->addIndex('primary', (array) $columns, $name);
    }

    /** Add created_at / updated_at timestamp columns. */
    public function timestamps(): self
    {
        return $this->timestamp('created_at', true)->timestamp('updated_at', true);
    }

    /** Add a soft delete timestamp column. */
    public function softDeletes(): self
    {
        return $this->timestamp('deleted_at', true);
    }

    /** Add a foreign key id column. */
    public function foreignId(string $column, bool $nullable = false): self
    {
        return $this->column($this->compileColumn($column, 'BIGINT UNSIGNED', $nullable, null));
    }

    /** Drop a column from the table. */
    public function dropColumn(string $column): self
    {
        $this->operations[] = sprintf('DROP COLUMN `%s`', $column);

        return $this;
    }

    /** Rename a column. */
    public function renameColumn(string $from, string $to): self
    {
        $this->operations[] = sprintf('RENAME COLUMN `%s` TO `%s`', $from, $to);

        return $this;
    }

    /** Add a raw column/operation definition. */
    public function raw(string $definition): self
    {
        return $this->column($definition);
    }

    /** Render SQL statements for the blueprint. */
    public function toSql(): array
    {
        if ($this->action === 'create') {
            $definitions = array_merge($this->columns, $this->indexes);
            $columns = implode(",\n    ", $definitions);

            return [sprintf("CREATE TABLE `%s` (\n    %s\n)", $this->table, $columns)];
        }

        if (empty($this->operations)) {
            return [];
        }

        return [sprintf('ALTER TABLE `%s` %s', $this->table, implode(', ', $this->operations))];
    }

    protected function addIndex(string $type, array $columns, ?string $name): self
    {
        $columns = array_map(fn ($column) => trim($column), $columns);
        $name = $name ?: $this->generateIndexName($type, $columns);
        $columnList = implode(', ', array_map(fn ($column) => '`' . $column . '`', $columns));

        $type = strtolower($type);
        $definition = match ($type) {
            'primary' => sprintf('PRIMARY KEY (`%s`)', implode('`, `', $columns)),
            'unique' => sprintf('UNIQUE KEY `%s` (%s)', $name, $columnList),
            default => sprintf('KEY `%s` (%s)', $name, $columnList),
        };

        if ($this->action === 'create') {
            $this->indexes[] = $definition;
        } else {
            if ($type === 'primary') {
                $this->operations[] = sprintf('ADD PRIMARY KEY (%s)', $columnList);
            } elseif ($type === 'unique') {
                $this->operations[] = sprintf('ADD UNIQUE KEY `%s` (%s)', $name, $columnList);
            } else {
                $this->operations[] = sprintf('ADD INDEX `%s` (%s)', $name, $columnList);
            }
        }

        return $this;
    }

    protected function generateIndexName(string $type, array $columns): string
    {
        $base = $this->table . '_' . implode('_', $columns);
        $suffix = match (strtolower($type)) {
            'primary' => 'primary',
            'unique' => 'unique',
            default => 'index',
        };

        $name = strtolower($base . '_' . $suffix);

        return substr($name, 0, 64);
    }

    protected function column(string $definition): self
    {
        if ($this->action === 'create') {
            $this->columns[] = $definition;
        } else {
            $this->operations[] = 'ADD COLUMN ' . $definition;
        }

        return $this;
    }

    protected function compileColumn(string $column, string $type, bool $nullable, mixed $default): string
    {
        $definition = sprintf('`%s` %s', $column, $type);
        $definition .= $nullable ? ' NULL' : ' NOT NULL';

        if ($default !== null) {
            if (is_string($default)) {
                $upper = strtoupper($default);
                $shouldQuote = !in_array($upper, ['CURRENT_TIMESTAMP', 'CURRENT_DATE', 'CURRENT_TIME'], true);
                if ($shouldQuote) {
                    $default = "'" . addslashes($default) . "'";
                } else {
                    $default = $upper;
                }
            } elseif (is_bool($default)) {
                $default = $default ? '1' : '0';
            }

            $definition .= ' DEFAULT ' . $default;
        }

        return $definition;
    }
}
