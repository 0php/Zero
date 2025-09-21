<?php

declare(strict_types=1);

namespace Zero\Lib\DB;

use Closure;
use InvalidArgumentException;
use RuntimeException;
use Zero\Lib\Database;
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
     * Apply a WHERE clause; accepts column/operator/value triples, arrays, or closures for nesting.
     */
    public function where(string|array|Closure $column, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): self
    {
        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        if (is_array($column)) {
            foreach ($column as $key => $item) {
                $this->where($key, '=', $item, $boolean);
            }
            return $this;
        }

        if ($value === null && func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        if ($value === null) {
            return $this->whereNull($column, $boolean);
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => strtoupper((string)($operator ?? '=')),
            'boolean' => strtoupper($boolean),
        ];

        $this->addBinding($value);

        return $this;
    }

    /**
     * Add an OR WHERE clause.
     */
    public function orWhere(string|array|Closure $column, mixed $operator = null, mixed $value = null): self
    {
        return $this->where($column, $operator, $value, 'OR');
    }

    public function whereNot(string $column, mixed $value, string $boolean = 'AND'): self
    {
        return $this->where($column, '!=', $value, $boolean);
    }

    public function orWhereNot(string $column, mixed $value): self
    {
        return $this->whereNot($column, $value, 'OR');
    }

    /**
     * Constrain the query to rows where the column value is within the provided list.
     */
    public function whereIn(string $column, array $values, string $boolean = 'AND', bool $not = false): self
    {
        if (empty($values)) {
            return $not ? $this : $this->whereRaw('1 = 0', [], $boolean);
        }

        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => array_values($values),
            'boolean' => strtoupper($boolean),
            'not' => $not,
        ];

        $this->addBinding($values);

        return $this;
    }

    public function whereNotIn(string $column, array $values, string $boolean = 'AND'): self
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function orWhereIn(string $column, array $values): self
    {
        return $this->whereIn($column, $values, 'OR');
    }

    public function orWhereNotIn(string $column, array $values): self
    {
        return $this->whereNotIn($column, $values, 'OR');
    }

    public function whereInSet(string $column, array $values, string $boolean = 'AND'): self
    {
        return $this->addWhereInSet($column, $values, $boolean, false);
    }

    public function whereNotInSet(string $column, array $values, string $boolean = 'AND'): self
    {
        return $this->addWhereInSet($column, $values, $boolean, true);
    }

    public function orWhereInSet(string $column, array $values): self
    {
        return $this->whereInSet($column, $values, 'OR');
    }

    public function orWhereNotInSet(string $column, array $values): self
    {
        return $this->whereNotInSet($column, $values, 'OR');
    }

    protected function addWhereInSet(string $column, array $values, string $boolean, bool $not): self
    {
        if (empty($values)) {
            return $not ? $this : $this->whereRaw('1 = 0', [], $boolean);
        }

        $values = array_values($values);

        $this->wheres[] = [
            'type' => 'in_set',
            'column' => $column,
            'values' => $values,
            'boolean' => strtoupper($boolean),
            'not' => $not,
        ];

        $this->addBinding($values);

        return $this;
    }

    public function whereBetween(string $column, array $values, string $boolean = 'AND', bool $not = false): self
    {
        if (count($values) !== 2) {
            throw new InvalidArgumentException('Between requires exactly two values.');
        }

        $range = array_values($values);

        $this->wheres[] = [
            'type' => 'between',
            'column' => $column,
            'boolean' => strtoupper($boolean),
            'not' => $not,
        ];

        $this->addBinding([$range[0], $range[1]]);

        return $this;
    }

    public function whereNotBetween(string $column, array $values, string $boolean = 'AND'): self
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    public function orWhereBetween(string $column, array $values): self
    {
        return $this->whereBetween($column, $values, 'OR');
    }

    public function orWhereNotBetween(string $column, array $values): self
    {
        return $this->whereNotBetween($column, $values, 'OR');
    }

    public function whereNull(string $column, string $boolean = 'AND', bool $not = false): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => strtoupper($boolean),
            'not' => $not,
        ];

        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'AND'): self
    {
        return $this->whereNull($column, $boolean, true);
    }

    public function orWhereNull(string $column): self
    {
        return $this->whereNull($column, 'OR');
    }

    public function orWhereNotNull(string $column): self
    {
        return $this->whereNotNull($column, 'OR');
    }

    public function whereRaw(string $expression, array $bindings = [], string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'type' => 'raw',
            'sql' => $expression,
            'boolean' => strtoupper($boolean),
        ];

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

    protected function whereNested(Closure $callback, string $boolean): self
    {
        $nested = static::table($this->table . ($this->alias ? ' as ' . $this->alias : ''));
        $callback($nested);

        if (empty($nested->wheres)) {
            return $this;
        }

        $this->wheres[] = [
            'type' => 'nested',
            'query' => $nested,
            'boolean' => strtoupper($boolean),
        ];

        $this->addBinding($nested->bindings);

        return $this;
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

    protected function normalizeColumns(array $columns): array
    {
        if (count($columns) === 1 && is_array($columns[0])) {
            $columns = $columns[0];
        }

        $normalized = [];

        foreach ($columns as $column) {
            if ($column instanceof DBMLExpression) {
                $normalized[] = $column;
                continue;
            }

            if (is_string($column)) {
                $trimmed = trim($column);
                if ($trimmed !== '') {
                    $normalized[] = $trimmed;
                }
            }
        }

        return $normalized;
    }

    protected function compileColumns(): string
    {
        $columns = $this->columns ?: ['*'];
        $compiled = [];

        foreach ($columns as $column) {
            $compiled[] = $this->wrapColumnForSelect($column);
        }

        return 'SELECT ' . implode(', ', $compiled);
    }

    protected function compileFrom(): string
    {
        return 'FROM ' . $this->wrapTable($this->table, $this->alias);
    }

    protected function compileJoins(): ?string
    {
        if (empty($this->joins)) {
            return null;
        }

        $segments = [];

        foreach ($this->joins as $join) {
            $segments[] = sprintf(
                '%s JOIN %s ON %s %s %s',
                $join['type'],
                $this->wrapTable($join['table'], $join['alias']),
                $this->wrap($join['first']),
                $join['operator'] ?? '=',
                $this->wrap((string) $join['second'])
            );
        }

        return implode(' ', $segments);
    }

    protected function compileWheres(): ?string
    {
        if (empty($this->wheres)) {
            return null;
        }

        $parts = [];

        foreach ($this->wheres as $index => $where) {
            $boolean = $index === 0 ? '' : $where['boolean'] . ' ';

            switch ($where['type']) {
                case 'basic':
                    $parts[] = $boolean . $this->wrap($where['column']) . ' ' . $where['operator'] . ' ?';
                    break;
                case 'raw':
                    $parts[] = $boolean . $where['sql'];
                    break;
                case 'in':
                    $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                    $parts[] = sprintf(
                        '%s%s %sIN (%s)',
                        $boolean,
                        $this->wrap($where['column']),
                        $where['not'] ? 'NOT ' : '',
                        $placeholders
                    );
                    break;
                case 'in_set':
                    $wrappedColumn = $this->wrap($where['column']);
                    $operator = $where['not'] ? '= 0' : '> 0';
                    $glue = $where['not'] ? ' AND ' : ' OR ';
                    $comparisons = [];

                    foreach ($where['values'] as $_) {
                        $comparisons[] = sprintf('FIND_IN_SET(?, %s) %s', $wrappedColumn, $operator);
                    }

                    $parts[] = sprintf('%s(%s)', $boolean, implode($glue, $comparisons));
                    break;
                case 'between':
                    $parts[] = sprintf(
                        '%s%s %sBETWEEN ? AND ?',
                        $boolean,
                        $this->wrap($where['column']),
                        $where['not'] ? 'NOT ' : ''
                    );
                    break;
                case 'null':
                    $parts[] = sprintf(
                        '%s%s IS %sNULL',
                        $boolean,
                        $this->wrap($where['column']),
                        $where['not'] ? 'NOT ' : ''
                    );
                    break;
                case 'nested':
                    $nestedSql = $where['query']->compileWheres();
                    if ($nestedSql) {
                        $nestedSql = preg_replace('/^WHERE\s+/i', '', $nestedSql);
                        $parts[] = $boolean . '(' . $nestedSql . ')';
                    }
                    break;
                default:
                    throw new RuntimeException('Unsupported WHERE clause type: ' . $where['type']);
            }
        }

        return 'WHERE ' . implode(' ', $parts);
    }

    protected function compileGroupBy(): ?string
    {
        if (empty($this->groups)) {
            return null;
        }

        $columns = array_map(fn ($column) => $this->wrapColumnForSelect($column), $this->groups);

        return 'GROUP BY ' . implode(', ', $columns);
    }

    protected function compileHaving(): ?string
    {
        if (empty($this->havings)) {
            return null;
        }

        $parts = [];

        foreach ($this->havings as $index => $having) {
            $boolean = $index === 0 ? '' : $having['boolean'] . ' ';

            switch ($having['type']) {
                case 'basic':
                    $parts[] = $boolean . $this->wrap($having['column']) . ' ' . $having['operator'] . ' ?';
                    break;
                case 'raw':
                    $parts[] = $boolean . $having['sql'];
                    break;
                case 'nested':
                    $nestedSql = $having['query']->compileHaving();
                    if ($nestedSql) {
                        $nestedSql = preg_replace('/^HAVING\s+/i', '', $nestedSql);
                        $parts[] = $boolean . '(' . $nestedSql . ')';
                    }
                    break;
                default:
                    throw new RuntimeException('Unsupported HAVING clause type: ' . $having['type']);
            }
        }

        return 'HAVING ' . implode(' ', $parts);
    }

    protected function compileOrderBy(): ?string
    {
        if (empty($this->orders)) {
            return null;
        }

        $segments = [];

        foreach ($this->orders as $order) {
            if (($order['type'] ?? null) === 'raw') {
                $segments[] = $order['sql'];
                continue;
            }

            $segments[] = $this->wrapColumnForSelect($order['column']) . ' ' . $order['direction'];
        }

        return 'ORDER BY ' . implode(', ', $segments);
    }

    protected function compileLimit(): ?string
    {
        if ($this->limit === null) {
            return null;
        }

        return 'LIMIT ' . $this->limit;
    }

    protected function compileOffset(): ?string
    {
        if ($this->offset === null) {
            return null;
        }

        return 'OFFSET ' . $this->offset;
    }

    protected function wrapColumnForSelect(string|DBMLExpression $column): string
    {
        if ($column instanceof DBMLExpression) {
            return (string) $column;
        }

        if (preg_match('/\s+as\s+/i', $column)) {
            [$name, $alias] = preg_split('/\s+as\s+/i', $column, 2);
            return $this->wrap(trim($name)) . ' AS ' . $this->wrapValue(trim($alias));
        }

        return $this->wrap($column);
    }

    protected function wrapTable(string $table, ?string $alias = null): string
    {
        $wrapped = $this->wrap($table);

        if ($alias) {
            $wrapped .= ' AS ' . $this->wrapValue($alias);
        }

        return $wrapped;
    }

    protected function wrap(string $value): string
    {
        $value = trim($value);

        if ($value === '*') {
            return '*';
        }

        if ($this->isExpression($value)) {
            return $value;
        }

        $segments = explode('.', $value);
        $segments = array_map([$this, 'wrapValue'], $segments);

        return implode('.', $segments);
    }

    protected function wrapValue(string $value): string
    {
        $value = trim($value, " \"`\'");

        if ($value === '*') {
            return '*';
        }

        return '`' . str_replace('`', '``', $value) . '`';
    }

    protected function isExpression(string $value): bool
    {
        return str_contains($value, '(') || str_contains($value, ')') || str_contains($value, ' ');
    }

    protected function parseTableAlias(string $table, ?string $alias = null): array
    {
        if ($alias !== null) {
            return [trim($table), trim($alias)];
        }

        if (preg_match('/\s+as\s+/i', $table)) {
            [$name, $as] = preg_split('/\s+as\s+/i', trim($table), 2);
            return [trim($name), trim($as)];
        }

        if (preg_match('/\s+/', trim($table))) {
            $parts = preg_split('/\s+/', trim($table), 2);
            if (count($parts) === 2) {
                return [trim($parts[0]), trim($parts[1])];
            }
        }

        return [trim($table), null];
    }

    protected function prepareInsertRows(array $values): array
    {
        if ($this->isAssoc($values)) {
            return [$values];
        }

        if ($this->isList($values) && isset($values[0]) && is_array($values[0])) {
            $columns = array_keys($values[0]);
            $columnKeys = array_flip($columns);
            $rows = [];

            foreach ($values as $row) {
                if (!$this->isAssoc($row)) {
                    throw new InvalidArgumentException('All insert rows must be associative arrays.');
                }

                if (array_diff_key($row, $columnKeys) || array_diff_key($columnKeys, $row)) {
                    throw new InvalidArgumentException('All insert rows must share the same columns.');
                }

                $ordered = [];
                foreach ($columns as $column) {
                    $ordered[$column] = $row[$column];
                }

                $rows[] = $ordered;
            }

            return $rows;
        }

        throw new InvalidArgumentException('Insert expects an associative array or an array of associative arrays.');
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

    protected function guessColumnAlias(string $column): string
    {
        if (preg_match('/\s+as\s+(.+)$/i', $column, $matches)) {
            return trim($matches[1], "`\" ");
        }

        if (str_contains($column, '.')) {
            return substr($column, strrpos($column, '.') + 1);
        }

        return trim($column, "`\" ");
    }

    protected function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    protected function isList(array $values): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($values);
        }

        $expectedKey = 0;
        foreach (array_keys($values) as $key) {
            if ($key !== $expectedKey) {
                return false;
            }
            $expectedKey++;
        }
        return true;
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
