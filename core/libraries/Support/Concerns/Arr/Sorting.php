<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Arr;

use InvalidArgumentException;

trait Sorting
{
    public static function sort(array $array, callable|string|null $callback = null): array
    {
        if ($callback === null) {
            asort($array);
            return $array;
        }
        $resolver = is_callable($callback)
            ? $callback
            : static fn($item) => self::dataGet($item, explode('.', $callback));
        uasort($array, static fn($a, $b) => $resolver($a) <=> $resolver($b));
        return $array;
    }

    public static function sortDesc(array $array, callable|string|null $callback = null): array
    {
        $sorted = self::sort($array, $callback);
        return array_reverse($sorted, true);
    }

    public static function sortRecursive(array $array, int $options = SORT_REGULAR, bool $descending = false): array
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = self::sortRecursive($value, $options, $descending);
            }
        }
        unset($value);

        if (self::isAssoc($array)) {
            $descending ? krsort($array, $options) : ksort($array, $options);
        } else {
            $descending ? rsort($array, $options) : sort($array, $options);
        }
        return $array;
    }

    public static function shuffle(array $array, ?int $seed = null): array
    {
        if ($seed !== null) {
            mt_srand($seed);
        }
        shuffle($array);
        if ($seed !== null) {
            mt_srand();
        }
        return $array;
    }

    public static function random(array $array, ?int $number = null, bool $preserveKeys = false): mixed
    {
        $count = count($array);
        $requested = $number ?? 1;

        if ($requested > $count) {
            throw new InvalidArgumentException("You requested {$requested} items, but there are only {$count} items available.");
        }

        if ($number === null) {
            return $array[array_rand($array)];
        }
        if ($number === 0) {
            return [];
        }

        $keys = (array) array_rand($array, $number);
        $results = [];
        if ($preserveKeys) {
            foreach ($keys as $key) {
                $results[$key] = $array[$key];
            }
        } else {
            foreach ($keys as $key) {
                $results[] = $array[$key];
            }
        }
        return $results;
    }
}
