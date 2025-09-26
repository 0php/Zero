<?php

declare(strict_types=1);

namespace Zero\Lib\DB\Concerns;

use InvalidArgumentException;
use RuntimeException;
use Zero\Lib\DB\DBMLExpression;

trait CompilesQueries
{
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
                case 'exists':
                    $operator = $where['not'] ? 'NOT EXISTS' : 'EXISTS';
                    $parts[] = sprintf('%s%s (%s)', $boolean, $operator, $where['query']->toSql());
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
