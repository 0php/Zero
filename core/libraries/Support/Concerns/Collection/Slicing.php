<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Collection;

use Zero\Lib\Support\Arr;

trait Slicing
{
    public function take(int $limit): static
    {
        return new static(Arr::take($this->items, $limit));
    }

    public function skip(int $count): static
    {
        return new static(array_slice($this->items, $count, null, true));
    }

    public function slice(int $offset, ?int $length = null): static
    {
        return new static(array_slice($this->items, $offset, $length, true));
    }

    public function chunk(int $size): static
    {
        if ($size <= 0) {
            return new static();
        }
        $chunks = [];
        foreach (array_chunk($this->items, $size, true) as $chunk) {
            $chunks[] = new static($chunk);
        }
        return new static($chunks);
    }

    public function nth(int $step, int $offset = 0): static
    {
        $new = [];
        $position = 0;
        foreach ($this->items as $item) {
            if ($position % $step === $offset) {
                $new[] = $item;
            }
            $position++;
        }
        return new static($new);
    }
}
