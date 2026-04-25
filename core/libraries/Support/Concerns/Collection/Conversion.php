<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Collection;

use ArrayIterator;
use JsonSerializable;
use Traversable;

trait Conversion
{
    public function all(): array
    {
        return $this->items;
    }

    public function toArray(): array
    {
        return array_map(static function ($value) {
            if ($value instanceof self) {
                return $value->toArray();
            }
            if ($value instanceof JsonSerializable) {
                return $value->jsonSerialize();
            }
            return $value;
        }, $this->items);
    }

    public function toJson(int $flags = 0): string
    {
        return json_encode($this->jsonSerialize(), $flags);
    }

    public function jsonSerialize(): array
    {
        return array_map(static function ($value) {
            if ($value instanceof JsonSerializable) {
                return $value->jsonSerialize();
            }
            if ($value instanceof self) {
                return $value->jsonSerialize();
            }
            return $value;
        }, $this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function isNotEmpty(): bool
    {
        return $this->items !== [];
    }

    public function keys(): static
    {
        return new static(array_keys($this->items));
    }

    public function values(): static
    {
        return new static(array_values($this->items));
    }

    public function offsetExists(mixed $offset): bool { return isset($this->items[$offset]); }
    public function offsetGet(mixed $offset): mixed { return $this->items[$offset]; }
    public function offsetSet(mixed $offset, mixed $value): void { $offset === null ? $this->items[] = $value : $this->items[$offset] = $value; }
    public function offsetUnset(mixed $offset): void { unset($this->items[$offset]); }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    protected static function getArrayableItems(mixed $items): array
    {
        if (is_array($items)) return $items;
        if ($items instanceof self) return $items->all();
        if ($items instanceof JsonSerializable) return (array) $items->jsonSerialize();
        if ($items instanceof Traversable) return iterator_to_array($items);
        return (array) $items;
    }
}
