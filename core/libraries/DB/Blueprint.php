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

    /** @var string[] */
    protected array $postCreateStatements = [];

    /** @var string[] */
    protected array $primaryColumns = [];

    /** @var string[] */
    protected array $tableConstraints = [];

    protected ?string $charset = null;
    protected ?string $collation = null;
    protected string $driver;

    public function __construct(
        protected string $table,
        protected string $action = 'table'
    ) {
        if (!in_array($this->action, ['create', 'table'], true)) {
            throw new InvalidArgumentException('Invalid blueprint action.');
        }

        $connection = config('database.connection');
        $connectionConfig = config('database.' . $connection) ?? [];
        if (!is_array($connectionConfig)) {
            $connectionConfig = (array) $connectionConfig;
        }

        $driver = (string) ($connectionConfig['driver'] ?? $connection);
        $this->driver = strtolower($driver);

        if ($this->driver === 'mysql') {
            $this->charset = $connectionConfig['charset'] ?? null;
            $this->collation = $connectionConfig['collation'] ?? null;
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

    /** Add a decimal column. */
    public function decimal(string $column, int $precision = 10, int $scale = 2, bool $nullable = false, mixed $default = null): ColumnDefinition
    {
        $definition = $this->addColumnDefinition($column, sprintf('DECIMAL(%d, %d)', $precision, $scale));

        if ($nullable) {
            $definition->nullable();
        }

        if (func_num_args() >= 5) {
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

    /** Add a long text column. */
    public function longText(string $column, bool $nullable = true): ColumnDefinition
    {
        $definition = $this->addColumnDefinition($column, 'LONGTEXT');

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

    /** Add a UUID column (CHAR(36)) optionally marking it primary. */
    public function uuid(string $column = 'uuid', bool $primary = false): ColumnDefinition
    {
        $definition = $this->addColumnDefinition($column, 'CHAR(36)');

        if ($primary) {
            $definition->primary();
        }

        return $definition;
    }

    /** Convenience helper for UUID primary keys. */
    public function uuidPrimary(string $column = 'id'): ColumnDefinition
    {
        return $this->uuid($column, true);
    }

    /** Add a DATE column definition. */
    public function date(string $column, bool $nullable = false, mixed $default = null): ColumnDefinition
    {
        $definition = $this->addColumnDefinition($column, 'DATE');

        if ($nullable) {
            $definition->nullable();
        }

        if (func_num_args() >= 3) {
            $definition->default($default);
        }

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
        $columns = (array) $columns;
        $this->registerPrimaryColumns($columns);

        return $this->addIndex('primary', $columns, $name);
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

    public function charset(string $charset): self
    {
        if ($this->driver !== 'mysql') {
            return $this;
        }

        if ($this->action === 'create') {
            $this->charset = $charset;
        } else {
            $this->operations[] = sprintf('DEFAULT CHARACTER SET = %s', $charset);
        }

        return $this;
    }

    public function collation(string $collation): self
    {
        if ($this->driver !== 'mysql') {
            return $this;
        }

        if ($this->action === 'create') {
            $this->collation = $collation;
        } else {
            $this->operations[] = sprintf('COLLATE = %s', $collation);
        }

        return $this;
    }

    protected function isSqlite(): bool
    {
        return in_array($this->driver, ['sqlite', 'sqlite3'], true);
    }

    public function registerPrimaryColumns(array $columns): void
    {
        foreach ($columns as $column) {
            if (!in_array($column, $this->primaryColumns, true)) {
                $this->primaryColumns[] = $column;
            }
        }
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function isCreating(): bool
    {
        return $this->action === 'create';
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function getDefaultCharset(): ?string
    {
        return $this->charset;
    }

    public function getDefaultCollation(): ?string
    {
        return $this->collation;
    }

    /** Render SQL statements for the blueprint. */
    public function toSql(): array
    {
        if ($this->action === 'create') {
            $definitions = [];

            foreach ($this->columns as $column) {
                if ($column instanceof ColumnDefinition) {
                    $definitions[] = $column->toSql();
                    if ($foreign = $column->compileForeignKey()) {
                        $this->tableConstraints[] = $foreign;
                    }
                    continue;
                }

                $definitions[] = $column;
            }

            $definitions = array_merge($definitions, $this->tableConstraints, $this->indexes);
            $columns = implode(",
", $definitions);

            $statement = sprintf("CREATE TABLE `%s` (
    %s
)", $this->table, $columns);

            if ($this->driver === 'mysql') {
                $options = [];

                if ($this->charset !== null) {
                    $options[] = 'DEFAULT CHARACTER SET ' . $this->charset;
                }

                if ($this->collation !== null) {
                    $options[] = 'COLLATE ' . $this->collation;
                }

                if (!empty($options)) {
                    $statement .= ' ' . implode(' ', $options);
                }
            }

            $statements = [$statement];

            if (!empty($this->postCreateStatements)) {
                $statements = array_merge($statements, $this->postCreateStatements);
            }

            return $statements;
        }

        $operations = [];

        foreach ($this->operations as $operation) {
            if ($operation instanceof ColumnDefinition) {
                $keyword = $operation->isChange() ? 'MODIFY COLUMN ' : 'ADD COLUMN ';
                $operations[] = $keyword . $operation->toSql();
                if (!$operation->isChange() && ($foreign = $operation->compileForeignKey())) {
                    $operations[] = $foreign;
                }
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

        if ($type === 'primary') {
            $this->registerPrimaryColumns($columns);
        }

        if ($this->isSqlite()) {
            if ($this->action === 'create') {
                if ($type === 'primary') {
                    $this->indexes[] = sprintf('PRIMARY KEY (%s)', $columnList);
                } elseif ($type === 'unique') {
                    $this->indexes[] = sprintf('CONSTRAINT `%s` UNIQUE (%s)', $name, $columnList);
                } else {
                    $this->postCreateStatements[] = sprintf(
                        'CREATE INDEX `%s` ON `%s` (%s)',
                        $name,
                        $this->table,
                        $columnList
                    );
                }
            } else {
                if ($type === 'primary') {
                    $this->operations[] = sprintf('ADD PRIMARY KEY (%s)', $columnList);
                } elseif ($type === 'unique') {
                    $this->operations[] = sprintf('ADD CONSTRAINT `%s` UNIQUE (%s)', $name, $columnList);
                } else {
                    $this->operations[] = sprintf('ADD INDEX `%s` (%s)', $name, $columnList);
                }
            }

            return $this;
        }

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
    private ?string $foreignTable = null;
    private ?string $foreignColumn = null;
    private ?string $onDelete = null;
    private ?string $onUpdate = null;
    private ?string $foreignName = null;
    private ?string $charset = null;
    private ?string $collation = null;
    private bool $change = false;
    private bool $primaryKey = false;
    private bool $autoIncrement = false;

    public function __construct(
        private Blueprint $blueprint,
        private string $column,
        private string $type
    ) {
        $upperType = strtoupper($type);
        $this->autoIncrement = str_contains($upperType, 'AUTO_INCREMENT');
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
        $this->primaryKey = true;

        if ($this->isSqlite()) {
            $this->blueprint->registerPrimaryColumns([$this->column]);
        } else {
            $this->blueprint->primary($this->column, $name);
        }

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

    public function references(string $column): self
    {
        $this->foreignColumn = $column;
        $this->registerForeignKey();

        return $this;
    }

    public function on(string $table): self
    {
        $this->foreignTable = $table;
        $this->registerForeignKey();

        return $this;
    }

    public function constrained(?string $table = null, string $column = 'id'): self
    {
        if ($table === null) {
            $table = $this->guessForeignTableFromColumn();
        }

        $this->references($column);
        $this->on($table);

        return $this;
    }

    public function onDelete(string $action): self
    {
        $this->onDelete = strtoupper($action);
        $this->registerForeignKey();

        return $this;
    }

    public function onUpdate(string $action): self
    {
        $this->onUpdate = strtoupper($action);
        $this->registerForeignKey();

        return $this;
    }

    public function foreignKeyName(string $name): self
    {
        $this->foreignName = $name;
        $this->registerForeignKey();

        return $this;
    }

    public function useCurrent(): self
    {
        return $this->default('CURRENT_TIMESTAMP');
    }

    public function charset(string $charset): self
    {
        if ($this->blueprint->getDriver() !== 'mysql') {
            return $this;
        }

        $this->charset = $charset;

        return $this;
    }

    public function collation(string $collation): self
    {
        if ($this->blueprint->getDriver() !== 'mysql') {
            return $this;
        }

        $this->collation = $collation;

        return $this;
    }

    public function collate(string $collation): self
    {
        return $this->collation($collation);
    }

    public function change(): self
    {
        $this->change = true;

        return $this;
    }

    public function toSql(): string
    {
        $driver = strtolower($this->blueprint->getDriver());
        $type = $this->compileType();
        $definition = sprintf('`%s` %s', $this->column, $type);
        $skipNullDefault = false;

        if ($this->primaryKey && $this->isSqlite()) {
            if ($this->autoIncrement) {
                $definition = sprintf('`%s` INTEGER PRIMARY KEY AUTOINCREMENT', $this->column);
            } else {
                $definition .= ' PRIMARY KEY';
            }

            $skipNullDefault = true;
        }

        if ($this->charset !== null && $driver === 'mysql') {
            $definition .= ' CHARACTER SET ' . $this->charset;
        }

        if ($this->collation !== null && $driver === 'mysql') {
            $definition .= ' COLLATE ' . $this->collation;
        }

        if ($skipNullDefault) {
            if ($this->defaultSet && !$this->autoIncrement) {
                $definition .= ' DEFAULT ' . $this->formatDefault($this->defaultValue);
            }

            return $definition;
        }

        $definition .= $this->nullable ? ' NULL' : ' NOT NULL';

        if ($this->defaultSet) {
            $definition .= ' DEFAULT ' . $this->formatDefault($this->defaultValue);
        }

        return $definition;
    }

    public function compileForeignKey(): ?string
    {
        if ($this->change) {
            return null;
        }

        if ($this->foreignTable === null || $this->foreignColumn === null) {
            return null;
        }

        $name = $this->foreignName ?? $this->generateForeignKeyName();
        $constraint = sprintf(
            'CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`)',
            $name,
            $this->column,
            $this->foreignTable,
            $this->foreignColumn
        );

        if ($this->onDelete !== null) {
            $constraint .= ' ON DELETE ' . $this->onDelete;
        }

        if ($this->onUpdate !== null) {
            $constraint .= ' ON UPDATE ' . $this->onUpdate;
        }

        if ($this->blueprint->isCreating()) {
            return $constraint;
        }

        return 'ADD ' . $constraint;
    }

    protected function compileType(): string
    {
        $type = $this->type;

        $driver = strtolower($this->blueprint->getDriver());

        if (in_array($driver, ['sqlite', 'sqlite3'], true)) {
            return $this->mapSqliteType($type);
        }

        if ($this->unsigned && !str_contains(strtoupper($type), 'UNSIGNED')) {
            $type .= ' UNSIGNED';
        }

        return $type;
    }

    public function isChange(): bool
    {
        return $this->change;
    }

    public function getColumn(): string
    {
        return $this->column;
    }

    protected function registerForeignKey(): void
    {
        if ($this->foreignTable === null || $this->foreignColumn === null) {
            return;
        }

        $this->foreignName ??= $this->generateForeignKeyName();
    }

    protected function generateForeignKeyName(): string
    {
        $table = $this->blueprint->getTable();
        $base = sprintf('fk_%s_%s', $table, $this->column);

        return substr($base, 0, 64);
    }

    protected function guessForeignTableFromColumn(): string
    {
        $column = $this->column;

        if (str_ends_with($column, '_id')) {
            $column = substr($column, 0, -3);
        }

        if (!str_ends_with($column, 's')) {
            $column .= 's';
        }

        return $column;
    }

    private function mapSqliteType(string $type): string
    {
        $upper = strtoupper($type);

        if ($this->autoIncrement || str_contains($upper, 'AUTO_INCREMENT')) {
            return 'INTEGER';
        }

        if (str_starts_with($upper, 'BIGINT') || str_starts_with($upper, 'INT') || str_starts_with($upper, 'TINYINT')) {
            return 'INTEGER';
        }

        if (str_starts_with($upper, 'VARCHAR') || str_starts_with($upper, 'CHAR')) {
            return 'TEXT';
        }

        if (
            str_starts_with($upper, 'LONGTEXT') ||
            str_starts_with($upper, 'MEDIUMTEXT') ||
            str_starts_with($upper, 'TEXT')
        ) {
            return 'TEXT';
        }

        if (str_starts_with($upper, 'ENUM')) {
            return 'TEXT';
        }

        if ($upper === 'BOOLEAN') {
            return 'INTEGER';
        }

        if ($upper === 'DATETIME' || $upper === 'TIMESTAMP') {
            return 'DATETIME';
        }

        return $type;
    }

    private function isSqlite(): bool
    {
        return in_array(strtolower($this->blueprint->getDriver()), ['sqlite', 'sqlite3'], true);
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
