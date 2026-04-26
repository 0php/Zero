<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Collection;

use Zero\Lib\Support\Arr;

trait Aggregates
{
    public function sum(callable|string|null $callback = null): int|float
    {
        if ($callback === null) {
            return array_sum($this->items);
        }
        $resolver = is_callable($callback) ? $callback : static fn($item) => is_array($item) ? ($item[$callback] ?? 0) : (is_object($item) ? ($item->{$callback} ?? 0) : 0);
        $total = 0;
        foreach ($this->items as $item) {
            $total += $resolver($item);
        }
        return $total;
    }

    public function avg(callable|string|null $callback = null): int|float|null
    {
        $count = $this->count();
        return $count > 0 ? $this->sum($callback) / $count : null;
    }

    public function average(callable|string|null $callback = null): int|float|null
    {
        return $this->avg($callback);
    }

    public function min(callable|string|null $callback = null): mixed
    {
        if ($callback === null) {
            return $this->items === [] ? null : min($this->items);
        }
        $resolver = is_callable($callback) ? $callback : static fn($item) => is_array($item) ? ($item[$callback] ?? null) : (is_object($item) ? ($item->{$callback} ?? null) : null);
        $values = array_map($resolver, $this->items);
        return $values === [] ? null : min($values);
    }

    public function max(callable|string|null $callback = null): mixed
    {
        if ($callback === null) {
            return $this->items === [] ? null : max($this->items);
        }
        $resolver = is_callable($callback) ? $callback : static fn($item) => is_array($item) ? ($item[$callback] ?? null) : (is_object($item) ? ($item->{$callback} ?? null) : null);
        $values = array_map($resolver, $this->items);
        return $values === [] ? null : max($values);
    }

    public function median(callable|string|null $callback = null): int|float|null
    {
        $values = $callback === null ? array_values($this->items) : array_values(array_map(
            is_callable($callback) ? $callback : static fn($item) => is_array($item) ? ($item[$callback] ?? null) : (is_object($item) ? ($item->{$callback} ?? null) : null),
            $this->items
        ));
        $values = array_filter($values, static fn($v) => is_numeric($v));
        if ($values === []) return null;
        sort($values);
        $count = count($values);
        $mid = intdiv($count, 2);
        return $count % 2 === 0 ? ($values[$mid - 1] + $values[$mid]) / 2 : $values[$mid];
    }

    public function implode(string $glue, ?string $key = null): string
    {
        if ($key === null) {
            return implode($glue, array_map(static fn($v) => (string) $v, $this->items));
        }
        return implode($glue, $this->pluck($key)->all());
    }

    public function join(string $glue, string $finalGlue = ''): string
    {
        return Arr::join(array_values($this->items), $glue, $finalGlue);
    }
}
