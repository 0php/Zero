<?php

declare(strict_types=1);

namespace Zero\Lib\DB\Concerns;

use Closure;
use InvalidArgumentException;
use Zero\Lib\DB\QueryBuilder;

trait HandlesWhereClauses
{
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

    public function whereExists(QueryBuilder $query, string $boolean = 'AND'): self
    {
        return $this->addWhereExists($query, $boolean, false);
    }

    public function orWhereExists(QueryBuilder $query): self
    {
        return $this->addWhereExists($query, 'OR', false);
    }

    public function whereNotExists(QueryBuilder $query, string $boolean = 'AND'): self
    {
        return $this->addWhereExists($query, $boolean, true);
    }

    public function orWhereNotExists(QueryBuilder $query): self
    {
        return $this->addWhereExists($query, 'OR', true);
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
    protected function addWhereExists(QueryBuilder $query, string $boolean, bool $not): self
    {
        $clone = clone $query;

        $this->wheres[] = [
            'type' => 'exists',
            'query' => $clone,
            'boolean' => strtoupper($boolean),
            'not' => $not,
        ];

        $this->addBinding($clone->getBindings());

        return $this;
    }
}
