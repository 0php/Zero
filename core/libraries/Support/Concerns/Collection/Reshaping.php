<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Collection;

use Zero\Lib\Support\Arr;

trait Reshaping
{
    public function collapse(): static
    {
        return new static(Arr::collapse($this->items));
    }

    public function flatten(int $depth = PHP_INT_MAX): static
    {
        return new static(Arr::flatten($this->items, $depth));
    }

    public function flip(): static
    {
        return new static(array_flip($this->items));
    }

    public function dot(): static
    {
        return new static(Arr::dot($this->items));
    }

    public function undot(): static
    {
        return new static(Arr::undot($this->items));
    }

    public function reverse(): static
    {
        return new static(array_reverse($this->items, true));
    }

    public function zip(iterable ...$items): static
    {
        $arrays = array_map(static fn($i) => self::getArrayableItems($i), $items);
        $results = [];
        foreach (array_values($this->items) as $i => $value) {
            $row = [$value];
            foreach ($arrays as $arr) {
                $row[] = array_values($arr)[$i] ?? null;
            }
            $results[] = new static($row);
        }
        return new static($results);
    }
}
