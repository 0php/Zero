<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Collection;

use Zero\Lib\Support\Arr;

trait Sorting
{
    public function sort(?callable $callback = null): static
    {
        $items = $this->items;
        $callback ? uasort($items, $callback) : asort($items);
        return new static($items);
    }

    public function sortBy(callable|string $callback, int $options = SORT_REGULAR, bool $descending = false): static
    {
        $resolver = is_callable($callback) ? $callback : static fn($item) => is_array($item) ? ($item[$callback] ?? null) : (is_object($item) ? ($item->{$callback} ?? null) : null);
        $items = $this->items;
        uasort($items, static function ($a, $b) use ($resolver, $descending, $options) {
            $aVal = $resolver($a);
            $bVal = $resolver($b);
            $cmp = $aVal <=> $bVal;
            return $descending ? -$cmp : $cmp;
        });
        return new static($items);
    }

    public function sortByDesc(callable|string $callback, int $options = SORT_REGULAR): static
    {
        return $this->sortBy($callback, $options, true);
    }

    public function sortDesc(): static
    {
        $items = $this->items;
        arsort($items);
        return new static($items);
    }

    public function sortKeys(int $options = SORT_REGULAR, bool $descending = false): static
    {
        $items = $this->items;
        $descending ? krsort($items, $options) : ksort($items, $options);
        return new static($items);
    }

    public function unique(callable|string|null $key = null, bool $strict = false): static
    {
        if ($key === null) {
            return new static(array_unique($this->items, SORT_REGULAR));
        }
        $resolver = is_callable($key) ? $key : static fn($item) => is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->{$key} ?? null) : null);
        $seen = [];
        $results = [];
        foreach ($this->items as $k => $item) {
            $id = $resolver($item, $k);
            if (! in_array($id, $seen, $strict)) {
                $seen[] = $id;
                $results[$k] = $item;
            }
        }
        return new static($results);
    }

    public function duplicates(): static
    {
        $counts = array_count_values(array_map(static fn($v) => is_scalar($v) ? (string) $v : serialize($v), $this->items));
        $results = [];
        foreach ($this->items as $k => $v) {
            $id = is_scalar($v) ? (string) $v : serialize($v);
            if ($counts[$id] > 1) {
                $results[$k] = $v;
            }
        }
        return new static($results);
    }

    public function shuffle(?int $seed = null): static
    {
        return new static(Arr::shuffle($this->items, $seed));
    }
}
