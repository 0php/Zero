<?php

declare(strict_types=1);

namespace Zero\Lib\Support;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Zero\Lib\Support\Concerns\Collection\Aggregates;
use Zero\Lib\Support\Concerns\Collection\Conditional;
use Zero\Lib\Support\Concerns\Collection\Conversion;
use Zero\Lib\Support\Concerns\Collection\Filtering;
use Zero\Lib\Support\Concerns\Collection\Iteration;
use Zero\Lib\Support\Concerns\Collection\Mutation;
use Zero\Lib\Support\Concerns\Collection\Querying;
use Zero\Lib\Support\Concerns\Collection\Reshaping;
use Zero\Lib\Support\Concerns\Collection\SetOperations;
use Zero\Lib\Support\Concerns\Collection\Slicing;
use Zero\Lib\Support\Concerns\Collection\Sorting;

/**
 * Fluent, chainable wrapper around arrays. Composed from topical traits under
 * Zero\Lib\Support\Concerns\Collection\*. The public API is unchanged from the
 * monolithic version — every Collection method you used before keeps working.
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @implements ArrayAccess<TKey, TValue>
 * @implements IteratorAggregate<TKey, TValue>
 *
 * @see docs/support/collection.md
 */
class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    use Conversion;
    use Iteration;
    use Filtering;
    use Querying;
    use Mutation;
    use Slicing;
    use Reshaping;
    use SetOperations;
    use Sorting;
    use Aggregates;
    use Conditional;

    /** @var array<TKey, TValue> */
    protected array $items;

    public function __construct(iterable $items = [])
    {
        $this->items = self::getArrayableItems($items);
    }

    public static function make(iterable $items = []): static
    {
        return new static($items);
    }

    public static function wrap(mixed $value): static
    {
        if ($value instanceof self) {
            return new static($value->all());
        }
        return new static(Arr::wrap($value));
    }

    public static function range(int $from, int $to): static
    {
        return new static(range($from, $to));
    }

    public static function times(int $number, ?callable $callback = null): static
    {
        if ($number < 1) {
            return new static();
        }
        $items = range(1, $number);
        return new static($callback === null ? $items : array_map($callback, $items));
    }
}
