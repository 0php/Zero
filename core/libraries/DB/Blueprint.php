<?php

declare(strict_types=1);

namespace Zero\Lib\DB;

use InvalidArgumentException;

/**
 * Fluent schema definition object used by {@see Schema} to compose SQL.
 */
class Blueprint
{
    /** @var array<int, string|ColumnDefinition> */
    protected array $columns = [];

    /** @var array<int, string|ColumnDefinition> */
    protected array $operations = [];

    /** @var string[] */
    protected array $indexes = [];

    public function __construct(
        protected string $table,
        protected string $action = 'table'
    ) {
        if (!in_array($this->action, ['create', 'table'], true)) {
            throw new InvalidArgumentException('Invalid blueprint action.');
        }
    }

    /** Add an auto-incrementing primary key column. */
    public function id(string $column = 'id'): ColumnDefinition
    {
        return $this->addColumnDefinition($column, 'BIGINT UNSIGNED AUTO_INCREMENT')
            ->nullable(false)
            ->primary();
    }

    public function increments(string $column): ColumnDefinition
    {
        return $this->id($column);
    }

    /** Add an integer column. */
    public function integer(string $column, bool $unsigned = false, bool $nullable = false, mixed $default = null): ColumnDefinition
    {
        $definition = $this->addColumnDefinition($column, 'INT');

        if ($unsigned) {
            $definition->unsigned();
        }

        if ($nullable) {
            $definition->nullable();
        }

        if (func_num_args() >= 4) {
            $definition->default($default);
        }

        return $definition;
    }

    /** Add a big integer column. */
    public function bigInteger(string $column, bool $unsigned = false, bool $nullable = false, mixed $default = null): ColumnDefinition
    {
        $definition = $this->addColumnDefinition($column, 'BIGINT');

        if ($unsigned) {
            $definition->unsigned();
        }

        if ($nullable) {
            $definition->nullable();
        }

        if (func_num_args() >= 4) {
            $definition->default($default);
        }

        return $definition;
    }

    /** Add a string/varchar column. */
    public function string(string $column, int $length = 255, bool $nullable = false, mixed $default = null): ColumnDefinition
    {
        $definition = $this->addColumnDefinition($column, sprintf('VARCHAR(%d)', $length));

        if ($nullable) {
            $definition->nullable();
        }

        if (func_num_args() >= 4) {
            $definition->default($default);
        }

        return $definition;
    }

    /** Add a text column. */
    public function text(string $column, bool $nullable = true): ColumnDefinition
    {
        $definition = $this->addColumnDefinition($column, 'TEXT');

        if ($nullable) {
            $definition->nullable();
        }

        return $definition;
    }

    /** Add an ENUM column. */
    public function enum(string $column, array $allowed, bool $nullable = false, mixed $default = null): ColumnDefinition
    {
        $options = implode(', ', array_map(static fn ($value) => "'" . addslashes((string) $value) . "'", $allowed));
        $definition = $this->addColumnDefinition($column, 'ENUM(' . $options . ')');

        if ($nullable) {
            $definition->nullable();
        }

        if ($default !== null) {
            $definition->default($default);
        }

        return $definition;
    }

    /** Add a boolean column. */
    public function boolean(string $column, bool $nullable = false, bool $default = false): ColumnDefinition
    {
        $definition = $this->addColumnDefinition($column, 'TINYINT(1)');

        if ($nullable) {
            $definition->nullable();
        }

        $definition->default($default);

        return $definition;
    }

    /** Add a timestamp column optionally allowing null/default values. */
    public function timestamp(string $column, bool $nullable = false, mixed $default = null): ColumnDefinition
    {
        $definition = $this->addColumnDefinition($column, 'TIMESTAMP');

        if ($nullable) {
            $definition->nullable();
        }

        if (func_num_args() >= 3) {
            $definition->default($default);
        }

        return $definition;
    }

    /** Add a DATETIME column definition. */
    public function datetime(string $column, bool $nullable = false, mixed $default = null): ColumnDefinition
    {
        $definition = $this->addColumnDefinition($column, 'DATETIME');

        if ($nullable) {
            $definition->nullable();
        }

        if (func_num_args() >= 3) {
            $definition->default($default);
        }

        return $definition;
    }

    /** Add created_at / updated_at timestamp columns. */
    public function timestamps(): self
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();

        return $this;
    }

    /** Add a soft delete timestamp column. */
    public function softDeletes(): self
    {
        $this->timestamp('deleted_at')->nullable();

        return $this;
    }

    /** Add a foreign key id column. */
    public function foreignId(string $column, bool $nullable = false): ColumnDefinition
    {
        $definition = $this->addColumnDefinition($column, 'BIGINT');
        $definition->unsigned();

        if ($nullable) {
            $definition->nullable();
        }

        return $definition;
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
        if ($this->action === 'create') {
            $this->columns[] = $definition;
        } else {
            $this->operations[] = $definition;
        }

        return $this;
    }

    /** Render SQL statements for the blueprint. */
    public function toSql(): array
    {
        if ($this->action === 'create') {
            $definitions = [];

            foreach ($this->columns as $column) {
                $definitions[] = $column instanceof ColumnDefinition ? $column->toSql() : $column;
            }

            $definitions = array_merge($definitions, $this->indexes);
            $columns = implode(",
    ", $definitions);

            return [sprintf("CREATE TABLE `%s` (
    %s
)", $this->table, $columns)];
        }

        $operations = [];

        foreach ($this->operations as $operation) {
            if ($operation instanceof ColumnDefinition) {
                $operations[] = 'ADD COLUMN ' . $operation->toSql();
            } else {
                $operations[] = $operation;
            }
        }

        if (empty($operations)) {
            return [];
        }

        return [sprintf('ALTER TABLE `%s` %s', $this->table, implode(', ', $operations))];
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

    protected function addColumnDefinition(string $column, string $type): ColumnDefinition
    {
        $definition = new ColumnDefinition($this, $column, $type);

        if ($this->action === 'create') {
            $this->columns[] = $definition;
        } else {
            $this->operations[] = $definition;
        }

        return $definition;
    }
}

class ColumnDefinition
{
    private bool $nullable = false;
    private bool $unsigned = false;
    private bool $defaultSet = false;
    private mixed $defaultValue = null;

    public function __construct(
        private Blueprint $blueprint,
        private string $column,
        private string $type
    ) {
    }

    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->defaultValue = $value;
        $this->defaultSet = true;

        return $this;
    }

    public function unsigned(bool $value = true): self
    {
        $this->unsigned = $value;

        return $this;
    }

    public function primary(?string $name = null): self
    {
        $this->blueprint->primary($this->column, $name);

        return $this;
    }

    public function unique(?string $name = null): self
    {
        $this->blueprint->unique($this->column, $name);

        return $this;
    }

    public function index(?string $name = null): self
    {
        $this->blueprint->index($this->column, $name);

        return $this;
    }

    public function useCurrent(): self
    {
        return $this->default('CURRENT_TIMESTAMP');
    }

    public function toSql(): string
    {
        $type = $this->compileType();
        $definition = sprintf('`%s` %s', $this->column, $type);
        $definition .= $this->nullable ? ' NULL' : ' NOT NULL';

        if ($this->defaultSet) {
            $definition .= ' DEFAULT ' . $this->formatDefault($this->defaultValue);
        }

        return $definition;
    }

    protected function compileType(): string
    {
        $type = $this->type;

        if ($this->unsigned && !str_contains(strtoupper($type), 'UNSIGNED')) {
            $type .= ' UNSIGNED';
        }

        return $type;
    }

    protected function formatDefault(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $value = (string) $value;
        $upper = strtoupper($value);

        if (in_array($upper, ['CURRENT_TIMESTAMP', 'CURRENT_DATE', 'CURRENT_TIME'], true)) {
            return $upper;
        }

        return "'" . addslashes($value) . "'";
    }
}
