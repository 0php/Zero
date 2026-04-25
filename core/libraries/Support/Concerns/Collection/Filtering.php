<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Collection;

use Zero\Lib\Support\Arr;

trait Filtering
{
    public function filter(?callable $callback = null): static
    {
        if ($callback === null) {
            return new static(array_filter($this->items));
        }
        return new static(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
    }

    public function reject(callable $callback): static
    {
        return $this->filter(static fn($v, $k) => ! $callback($v, $k));
    }

    public function where(string $key, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        return $this->filter(static function ($item) use ($key, $operator, $value) {
            $itemValue = is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->{$key} ?? null) : null);
            return match ($operator) {
                '=', '==' => $itemValue == $value,
                '===' => $itemValue === $value,
                '!=', '<>' => $itemValue != $value,
                '!==' => $itemValue !== $value,
                '>' => $itemValue > $value,
                '>=' => $itemValue >= $value,
                '<' => $itemValue < $value,
                '<=' => $itemValue <= $value,
                default => $itemValue == $value,
            };
        });
    }

    public function whereIn(string $key, iterable $values): static
    {
        $values = self::getArrayableItems($values);
        return $this->filter(static function ($item) use ($key, $values) {
            $itemValue = is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->{$key} ?? null) : null);
            return in_array($itemValue, $values, true);
        });
    }

    public function whereNotIn(string $key, iterable $values): static
    {
        $values = self::getArrayableItems($values);
        return $this->filter(static function ($item) use ($key, $values) {
            $itemValue = is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->{$key} ?? null) : null);
            return ! in_array($itemValue, $values, true);
        });
    }

    public function whereNotNull(?string $key = null): static
    {
        if ($key === null) {
            return new static(Arr::whereNotNull($this->items));
        }
        return $this->filter(static function ($item) use ($key) {
            $itemValue = is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->{$key} ?? null) : null);
            return $itemValue !== null;
        });
    }

    public function whereNull(?string $key = null): static
    {
        if ($key === null) {
            return $this->filter(static fn($v) => $v === null);
        }
        return $this->filter(static function ($item) use ($key) {
            $itemValue = is_array($item) ? ($item[$key] ?? null) : (is_object($item) ? ($item->{$key} ?? null) : null);
            return $itemValue === null;
        });
    }

    public function only(array|string $keys): static
    {
        return new static(Arr::only($this->items, $keys));
    }

    public function except(array|string $keys): static
    {
        return new static(Arr::except($this->items, $keys));
    }

    public function contains(mixed $key, mixed $operator = null, mixed $value = null): bool
    {
        if (func_num_args() === 1) {
            if (is_callable($key)) {
                foreach ($this->items as $k => $v) {
                    if ($key($v, $k)) return true;
                }
                return false;
            }
            return in_array($key, $this->items);
        }
        return $this->where($key, $operator, $value)->isNotEmpty();
    }

    public function has(string|int|array $key): bool
    {
        foreach ((array) $key as $k) {
            if (! array_key_exists($k, $this->items)) return false;
        }
        return true;
    }
}
