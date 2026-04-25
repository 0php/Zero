<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Collection;

trait Mutation
{
    public function put(string|int $key, mixed $value): static
    {
        $this->items[$key] = $value;
        return $this;
    }

    public function pull(string|int $key, mixed $default = null): mixed
    {
        $value = $this->items[$key] ?? value($default);
        unset($this->items[$key]);
        return $value;
    }

    public function push(mixed ...$values): static
    {
        foreach ($values as $value) {
            $this->items[] = $value;
        }
        return $this;
    }

    public function prepend(mixed $value, mixed $key = null): static
    {
        $this->items = $key === null
            ? array_merge([$value], $this->items)
            : [$key => $value] + $this->items;
        return $this;
    }

    public function pop(int $count = 1): mixed
    {
        if ($count === 1) return array_pop($this->items);
        $results = [];
        for ($i = 0; $i < $count && $this->items !== []; $i++) {
            $results[] = array_pop($this->items);
        }
        return new static($results);
    }

    public function shift(int $count = 1): mixed
    {
        if ($count === 1) return array_shift($this->items);
        $results = [];
        for ($i = 0; $i < $count && $this->items !== []; $i++) {
            $results[] = array_shift($this->items);
        }
        return new static($results);
    }

    public function forget(string|int|array $keys): static
    {
        foreach ((array) $keys as $key) {
            unset($this->items[$key]);
        }
        return $this;
    }
}
