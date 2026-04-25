<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Collection;

use Zero\Lib\Support\Arr;

trait Querying
{
    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        return Arr::first($this->items, $callback, $default);
    }

    public function last(?callable $callback = null, mixed $default = null): mixed
    {
        return Arr::last($this->items, $callback, $default);
    }

    public function get(string|int $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? value($default);
    }

    public function search(mixed $value, bool $strict = false): mixed
    {
        if (! is_callable($value)) {
            return array_search($value, $this->items, $strict);
        }
        foreach ($this->items as $key => $item) {
            if ($value($item, $key)) return $key;
        }
        return false;
    }

    public function random(?int $number = null, bool $preserveKeys = false): mixed
    {
        if ($number === null) {
            return $this->items[array_rand($this->items)];
        }
        return new static(Arr::random($this->items, $number, $preserveKeys));
    }
}
