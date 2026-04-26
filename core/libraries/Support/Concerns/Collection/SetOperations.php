<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Collection;

trait SetOperations
{
    public function merge(iterable $items): static
    {
        return new static(array_merge($this->items, self::getArrayableItems($items)));
    }

    public function mergeRecursive(iterable $items): static
    {
        return new static(array_merge_recursive($this->items, self::getArrayableItems($items)));
    }

    public function concat(iterable $source): static
    {
        $result = $this->items;
        foreach ($source as $value) {
            $result[] = $value;
        }
        return new static($result);
    }

    public function combine(iterable $values): static
    {
        return new static(array_combine($this->items, self::getArrayableItems($values)));
    }

    public function diff(iterable $items): static
    {
        return new static(array_diff($this->items, self::getArrayableItems($items)));
    }

    public function diffKeys(iterable $items): static
    {
        return new static(array_diff_key($this->items, self::getArrayableItems($items)));
    }

    public function intersect(iterable $items): static
    {
        return new static(array_intersect($this->items, self::getArrayableItems($items)));
    }

    public function intersectByKeys(iterable $items): static
    {
        return new static(array_intersect_key($this->items, self::getArrayableItems($items)));
    }
}
