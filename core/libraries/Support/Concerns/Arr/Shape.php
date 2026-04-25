<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Arr;

trait Shape
{
    public static function collapse(iterable $array): array
    {
        $results = [];
        foreach ($array as $values) {
            if (! is_array($values)) {
                continue;
            }
            $results = array_merge($results, $values);
        }
        return $results;
    }

    public static function flatten(iterable $array, int $depth = PHP_INT_MAX): array
    {
        $result = [];
        foreach ($array as $item) {
            if (! is_array($item)) {
                $result[] = $item;
                continue;
            }
            $values = $depth === 1 ? array_values($item) : self::flatten($item, $depth - 1);
            foreach ($values as $value) {
                $result[] = $value;
            }
        }
        return $result;
    }

    public static function dot(iterable $array, string $prepend = ''): array
    {
        $results = [];
        foreach ($array as $key => $value) {
            if (is_array($value) && $value !== []) {
                $results = array_merge($results, self::dot($value, $prepend . $key . '.'));
            } else {
                $results[$prepend . $key] = $value;
            }
        }
        return $results;
    }

    public static function undot(array $array): array
    {
        $results = [];
        foreach ($array as $key => $value) {
            self::set($results, $key, $value);
        }
        return $results;
    }

    public static function wrap(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        return is_array($value) ? $value : [$value];
    }

    public static function pluck(iterable $array, string|array|null $value, string|array|null $key = null): array
    {
        $results = [];
        $valuePath = is_string($value) ? explode('.', $value) : $value;
        $keyPath = is_string($key) ? explode('.', $key) : $key;

        foreach ($array as $item) {
            $itemValue = self::dataGet($item, $valuePath);
            if ($keyPath === null) {
                $results[] = $itemValue;
            } else {
                $itemKey = self::dataGet($item, $keyPath);
                if (is_object($itemKey) && method_exists($itemKey, '__toString')) {
                    $itemKey = (string) $itemKey;
                }
                $results[$itemKey] = $itemValue;
            }
        }
        return $results;
    }

    public static function keyBy(array $array, callable|string $keyBy): array
    {
        $results = [];
        foreach ($array as $item) {
            $key = is_callable($keyBy) ? $keyBy($item) : self::get($item, $keyBy);
            $results[$key] = $item;
        }
        return $results;
    }

    public static function only(array $array, array|string $keys): array
    {
        return array_intersect_key($array, array_flip((array) $keys));
    }

    public static function except(array $array, array|string $keys): array
    {
        self::forget($array, $keys);
        return $array;
    }

    public static function take(array $array, int $limit): array
    {
        if ($limit < 0) {
            return array_slice($array, $limit, abs($limit));
        }
        return array_slice($array, 0, $limit);
    }

    public static function prepend(array $array, mixed $value, mixed $key = null): array
    {
        if (func_num_args() === 2) {
            array_unshift($array, $value);
            return $array;
        }
        return [$key => $value] + $array;
    }

    public static function push(array $array, mixed ...$values): array
    {
        foreach ($values as $value) {
            $array[] = $value;
        }
        return $array;
    }

    public static function divide(array $array): array
    {
        return [array_keys($array), array_values($array)];
    }

    public static function crossJoin(array ...$arrays): array
    {
        $results = [[]];
        foreach ($arrays as $index => $array) {
            $append = [];
            foreach ($results as $product) {
                foreach ($array as $item) {
                    $product[$index] = $item;
                    $append[] = $product;
                }
            }
            $results = $append;
        }
        return $results;
    }
}
