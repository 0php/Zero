<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Collection;

use Zero\Lib\Support\Arr;

trait Iteration
{
    public function map(callable $callback): static
    {
        return new static(Arr::map($this->items, $callback));
    }

    public function mapWithKeys(callable $callback): static
    {
        return new static(Arr::mapWithKeys($this->items, $callback));
    }

    public function flatMap(callable $callback): static
    {
        return $this->map($callback)->collapse();
    }

    public function each(callable $callback): static
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }
        return $this;
    }

    public function eachSpread(callable $callback): static
    {
        return $this->each(static fn($chunk) => $callback(...array_values((array) $chunk)));
    }

    public function mapSpread(callable $callback): static
    {
        return $this->map(static fn($chunk) => $callback(...array_values((array) $chunk)));
    }

    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $result = $initial;
        foreach ($this->items as $key => $value) {
            $result = $callback($result, $value, $key);
        }
        return $result;
    }

    public function pluck(string|array|null $value, string|array|null $key = null): static
    {
        return new static(Arr::pluck($this->items, $value, $key));
    }

    public function partition(callable $callback): static
    {
        [$pass, $fail] = Arr::partition($this->items, $callback);
        return new static([new static($pass), new static($fail)]);
    }

    public function keyBy(callable|string $keyBy): static
    {
        return new static(Arr::keyBy($this->items, $keyBy));
    }

    public function groupBy(callable|string $groupBy): static
    {
        $results = [];
        foreach ($this->items as $key => $value) {
            $groupKey = is_callable($groupBy)
                ? $groupBy($value, $key)
                : (is_array($value) ? ($value[$groupBy] ?? null) : (is_object($value) ? ($value->{$groupBy} ?? null) : null));
            $results[$groupKey][] = $value;
        }
        $wrapped = [];
        foreach ($results as $k => $v) {
            $wrapped[$k] = new static($v);
        }
        return new static($wrapped);
    }
}
