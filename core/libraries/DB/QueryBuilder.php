<?php

declare(strict_types=1);

namespace Zero\Lib\DB;

use Closure;
use InvalidArgumentException;
use RuntimeException;
use Zero\Lib\Database;
use Zero\Lib\DB\Concerns\CompilesQueries;
use Zero\Lib\DB\Concerns\HandlesWhereClauses;
use Zero\Lib\Support\Paginator;

/**
 * Lightweight fluent query builder inspired by Laravel's Eloquent.
 *
 * This class focuses on composing SQL statements in a database-agnostic
 * manner and delegating execution to the underlying PDO bridge exposed via
 * the `Database` facade. All builder methods return `$this`, allowing chained
 * expressions such as:
 *
 * ```php
 * $users = DBML::table('users')
 *     ->select(['id', 'name'])
 *     ->where('active', 1)
 *     ->orderByDesc('created_at')
 *     ->limit(10)
 *     ->get();
 * ```
 */
class QueryBuilder
{
    use HandlesWhereClauses;
    use CompilesQueries;
    protected ?string $table = null;
    protected ?string $alias = null;
    protected array $columns = ['*'];
    protected array $joins = [];
    protected array $wheres = [];
    protected array $groups = [];
    protected array $orders = [];
    protected array $havings = [];
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected array $bindings = [];

    public function __construct()
    {
    }

    public function __clone(): void
    {
        foreach ($this->wheres as $index => $where) {
            if (($where['type'] ?? null) === 'nested' && isset($where['query'])) {
                $this->wheres[$index]['query'] = clone $where['query'];
            }
        }

        foreach ($this->havings as $index => $having) {
            if (($having['type'] ?? null) === 'nested' && isset($having['query'])) {
                $this->havings[$index]['query'] = clone $having['query'];
            }
        }
    }

    /**
     * Start a new query targeting the given table (optionally aliased).
     */
    public static function table(string $table, ?string $alias = null): self
    {
        $instance = new static();
        return $instance->from($table, $alias);
    }

    public function from(string $table, ?string $alias = null): self
    {
        [$tableName, $tableAlias] = $this->parseTableAlias($table, $alias);
        $this->table = $tableName;
        $this->alias = $tableAlias;
        return $this;
    }

    /**
     * Define the columns that should appear in the select clause.
     */
    public function select(string|array|DBMLExpression ...$columns): self
    {
        $normalized = $this->normalizeColumns($columns);
        $this->columns = $normalized ?: ['*'];
        return $this;
    }

    /**
     * Append additional select columns without resetting the existing list.
     */
    public function addSelect(string|array|DBMLExpression ...$columns): self
    {
        $normalized = $this->normalizeColumns($columns);

        if (empty($normalized)) {
            return $this;
        }

        if ($this->columns === ['*']) {
            $this->columns = [];
        }

        foreach ($normalized as $column) {
            $this->columns[] = $column;
        }

        return $this;
    }

    /**
     * Add a raw select expression (e.g., aggregate or database function call).
     */
    public function selectRaw(string $expression, array $bindings = []): self
    {
        $this->addSelect(self::raw($expression));
        $this->addBinding($bindings);
        return $this;
    }


    /**
     * Join the current table with another table using the specified join type.
     */
    public function join(string $table, string $first, ?string $operator = null, ?string $second = null, string $type = 'INNER', ?string $alias = null): self
    {
        if ($second === null) {
            throw new InvalidArgumentException('Join requires a second column.');
        }

        [$joinTable, $joinAlias] = $this->parseTableAlias($table, $alias);

        $this->joins[] = [
            'type' => strtoupper($type),
            'table' => $joinTable,
            'alias' => $joinAlias,
            'first' => $first,
            'operator' => $operator ?? '=',
            'second' => $second,
        ];

        return $this;
    }

    public function leftJoin(string $table, string $first, ?string $operator = null, ?string $second = null, ?string $alias = null): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT', $alias);
    }

    public function rightJoin(string $table, string $first, ?string $operator = null, ?string $second = null, ?string $alias = null): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT', $alias);
    }

    /**
     * Append an ORDER BY clause.
     */
    public function orderBy(string|DBMLExpression $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException('Order direction must be ASC or DESC.');
        }

        $this->orders[] = [
            'column' => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    public function orderByDesc(string|DBMLExpression $column): self
    {
        return $this->orderBy($column, 'DESC');
    }

    public function orderByRaw(string $expression): self
    {
        $this->orders[] = [
            'type' => 'raw',
            'sql' => $expression,
        ];

        return $this;
    }

    /**
     * Define the GROUP BY clause.
     */
    public function groupBy(string|array|DBMLExpression ...$columns): self
    {
        $normalized = $this->normalizeColumns($columns);

        foreach ($normalized as $column) {
            $this->groups[] = $column;
        }

        return $this;
    }

    /**
     * Apply a HAVING clause, supporting nested expressions via closures.
     */
    public function having(string|Closure $column, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): self
    {
        if ($column instanceof Closure) {
            return $this->havingNested($column, $boolean);
        }

        if ($value === null && func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        if ($value === null) {
            throw new InvalidArgumentException('HAVING requires a value.');
        }

        $this->havings[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => strtoupper((string)($operator ?? '=')),
            'boolean' => strtoupper($boolean),
        ];

        $this->addBinding($value);

        return $this;
    }

    public function havingRaw(string $expression, array $bindings = [], string $boolean = 'AND'): self
    {
        $this->havings[] = [
            'type' => 'raw',
            'sql' => $expression,
            'boolean' => strtoupper($boolean),
        ];

        $this->addBinding($bindings);

        return $this;
    }

    /**
     * Limit the number of rows returned.
     */
    public function limit(?int $value): self
    {
        $this->limit = $value === null ? null : max(0, $value);
        return $this;
    }

    /**
     * Skip a given number of rows before returning results.
     */
    public function offset(?int $value): self
    {
        $this->offset = $value === null ? null : max(0, $value);
        return $this;
    }

    /**
     * Convenience helper to paginate using page/per-page values.
     */
    public function forPage(int $page, int $perPage): self
    {
        $page = max(1, $page);
        $this->limit($perPage);
        $this->offset(($page - 1) * $perPage);
        return $this;
    }

    /**
     * Conditionally modify the query when the provided value evaluates truthy.
     */
    public function when(mixed $value, Closure $callback, ?Closure $default = null): self
    {
        if ($value) {
            $callback($this, $value);
        } elseif ($default) {
            $default($this, $value);
        }

        return $this;
    }

    /**
     * Execute the query and return all matching rows as an array.
     */
    public function get(array|string|DBMLExpression $columns = []): array
    {
        $query = clone $this;

        if (!empty($columns)) {
            $query->select($columns);
        }

        $sql = $query->toSql();
        $rows = Database::fetch($sql, $query->getBindings());

        return is_array($rows) ? $rows : [];
    }

    /**
     * Execute the query and return the first matching row (or null).
     */
    public function first(array|string|DBMLExpression $columns = []): array|null
    {
        $query = clone $this;
        $query->limit(1);

        if (!empty($columns)) {
            $query->select($columns);
        }

        $sql = $query->toSql();
        $result = Database::first($sql, $query->getBindings());

        if ($result === false || $result === null) {
            return null;
        }

        return $result;
    }

    /**
     * Update the first matching row or create it with the provided values.
     *
     * @return array<string, mixed>
     */
    public function updateOrCreate(array $attributes, array $values = []): array
    {
        if ($this->table === null) {
            throw new RuntimeException('Cannot update or create without a table name.');
        }

        if ($attributes === []) {
            throw new InvalidArgumentException('Update or create requires at least one identifying attribute.');
        }

        $search = clone $this;

        foreach ($attributes as $column => $value) {
            if (!is_string($column) || $column === '') {
                throw new InvalidArgumentException('Update or create attributes must use string column names.');
            }

            $search->where($column, $value);
        }

        $record = $search->first();

        if ($record !== null) {
            $payload = array_merge($attributes, $values);

            if ($payload !== []) {
                $search->update($payload);
                $record = $search->first() ?? array_merge($record, $payload);
            }

            return $record;
        }

        $payload = array_merge($attributes, $values);

        if ($payload === []) {
            throw new InvalidArgumentException('Update or create requires values to persist.');
        }

        $insertBuilder = clone $this;
        $insertBuilder->insert($payload);

        return $search->first() ?? $payload;
    }

    /**
     * Retrieve the first matching row or create it using the provided attributes.
     *
     * @return array<string, mixed>
     */
    public function findOrCreate(array $attributes, array $values = []): array
    {
        if ($this->table === null) {
            throw new RuntimeException('Cannot find or create without a table name.');
        }

        if ($attributes === []) {
            throw new InvalidArgumentException('Find or create requires at least one identifying attribute.');
        }

        $search = clone $this;

        foreach ($attributes as $column => $value) {
            if (!is_string($column) || $column === '') {
                throw new InvalidArgumentException('Find or create attributes must use string column names.');
            }

            $search->where($column, $value);
        }

        $record = $search->first();

        if ($record !== null) {
            return $record;
        }

        $payload = array_merge($attributes, $values);

        if ($payload === []) {
            throw new InvalidArgumentException('Find or create requires values to persist.');
        }

        $insertBuilder = clone $this;
        $insertBuilder->insert($payload);

        return $search->first() ?? $payload;
    }

    /**
     * Fetch a single column value from the first matching row.
     */
    public function value(string $column): mixed
    {
        $row = $this->first([$column]);

        if (!$row) {
            return null;
        }

        return $row[$this->guessColumnAlias($column)] ?? null;
    }

    /**
     * Retrieve a flat list of column values, optionally keyed by another column.
     */
    public function pluck(string $column, ?string $key = null): array
    {
        $columns = [$column];

        if ($key !== null) {
            $columns[] = $key;
        }

        $results = $this->get($columns);
        $values = [];

        foreach ($results as $row) {
            $valueKey = $this->guessColumnAlias($column);
            $value = $row[$valueKey] ?? null;

            if ($key !== null) {
                $keyName = $this->guessColumnAlias($key);
                $values[$row[$keyName] ?? null] = $value;
            } else {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * Determine whether the query yields any results.
     */
    public function exists(): bool
    {
        $query = clone $this;
        $query->select(self::raw('1'));
        $query->orders = [];
        $query->limit(1);
        $query->offset(null);

        $result = Database::first($query->toSql(), $query->getBindings());

        return $result !== false && $result !== null;
    }

    /**
     * Return a COUNT aggregate for the current query.
     */
    public function count(string $column = '*'): int
    {
        $query = clone $this;
        $query->select(self::raw('COUNT(' . ($column === '*' ? '*' : $query->wrap($column)) . ') AS aggregate'));
        $query->orders = [];
        $query->limit(null);
        $query->offset(null);

        $result = Database::first($query->toSql(), $query->getBindings());

        if (!is_array($result)) {
            return 0;
        }

        return (int) ($result['aggregate'] ?? 0);
    }

    /**
     * Insert one or many rows into the table and return the last insert id.
     */
    public function insert(array $values): mixed
    {
        if ($this->table === null) {
            throw new RuntimeException('Cannot insert without a table name.');
        }

        if (empty($values)) {
            throw new InvalidArgumentException('Insert values cannot be empty.');
        }

        $rows = $this->prepareInsertRows($values);
        $columns = array_keys($rows[0]);

        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $valueStrings = [];
        $bindings = [];

        foreach ($rows as $row) {
            $valueStrings[] = $placeholders;
            foreach ($columns as $column) {
                $bindings[] = $row[$column];
            }
        }

        $sql = 'INSERT INTO ' . $this->wrapTable($this->table) . ' (' . implode(', ', array_map([$this, 'wrap'], $columns)) . ') VALUES ' . implode(', ', $valueStrings);

        return Database::create($sql, $bindings);
    }

    /**
     * Update matching rows with the provided column/value pairs.
     */
    public function update(array $values): int
    {
        if ($this->table === null) {
            throw new RuntimeException('Cannot update without a table name.');
        }

        if (empty($values)) {
            throw new InvalidArgumentException('Update values cannot be empty.');
        }

        $sets = [];
        $bindings = [];

        foreach ($values as $column => $value) {
            $sets[] = $this->wrap($column) . ' = ?';
            $bindings[] = $value;
        }

        $sql = 'UPDATE ' . $this->wrapTable($this->table, $this->alias) . ' SET ' . implode(', ', $sets);
        $whereSql = $this->compileWheres();

        if ($whereSql) {
            $sql .= ' ' . $whereSql;
            $bindings = array_merge($bindings, $this->bindings);
        }

        return (int) Database::update($sql, $bindings);
    }

    /**
     * Delete matching rows from the table.
     */
    public function delete(): int
    {
        if ($this->table === null) {
            throw new RuntimeException('Cannot delete without a table name.');
        }

        $sql = 'DELETE FROM ' . $this->wrapTable($this->table);
        $whereSql = $this->compileWheres();

        if ($whereSql) {
            $sql .= ' ' . $whereSql;
        }

        return (int) Database::delete($sql, $this->bindings);
    }

    /**
     * Compile the current builder state into a raw SQL string.
     */
    public function toSql(): string
    {
        if ($this->table === null) {
            throw new RuntimeException('Table is not defined for the query.');
        }

        $components = [
            $this->compileColumns(),
            $this->compileFrom(),
            $this->compileJoins(),
            $this->compileWheres(),
            $this->compileGroupBy(),
            $this->compileHaving(),
            $this->compileOrderBy(),
            $this->compileLimit(),
            $this->compileOffset(),
        ];

        return trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($components))));
    }

    /**
     * Retrieve the positional bindings that accompany the compiled SQL.
     */
    public function getBindings(): array
    {
        return array_values($this->bindings);
    }

    /**
     * Create a raw SQL expression wrapper (used for columns and order clauses).
     */
    public static function raw(string $expression): DBMLExpression
    {
        return new DBMLExpression($expression);
    }

    /**
     * Paginate the query results.
     */
    public function paginate(int $perPage = 15, int $page = 1): Paginator
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $countQuery = clone $this;
        $countQuery->orders = [];
        $countQuery->limit(null);
        $countQuery->offset(null);

        $total = $countQuery->count();

        $results = clone $this;
        $results->limit($perPage);
        $results->offset(($page - 1) * $perPage);

        $items = $results->get();

        return new Paginator($items, $total, $perPage, $page);
    }

    /**
     * Simple pagination without performing an additional COUNT query.
     */
    public function simplePaginate(int $perPage = 15, int $page = 1): Paginator
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $results = clone $this;
        $results->limit($perPage);
        $results->offset(($page - 1) * $perPage);

        $items = $results->get();
        $count = count($items);
        $total = ($page - 1) * $perPage + $count;

        return new Paginator($items, $total, $perPage, $page);
    }


    protected function havingNested(Closure $callback, string $boolean): self
    {
        $nested = static::table($this->table . ($this->alias ? ' as ' . $this->alias : ''));
        $callback($nested);

        if (empty($nested->havings)) {
            return $this;
        }

        $this->havings[] = [
            'type' => 'nested',
            'query' => $nested,
            'boolean' => strtoupper($boolean),
        ];

        $this->addBinding($nested->bindings);

        return $this;
    }


    protected function addBinding(mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->bindings[] = $item;
            }
            return;
        }

        $this->bindings[] = $value;
    }



}

/**
 * Lightweight wrapper for raw SQL expressions.
 */
class DBMLExpression
{
    public function __construct(private string $value)
    {
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
