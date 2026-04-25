<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Arr;

trait Iteration
{
    public static function map(array $array, callable $callback): array
    {
        $keys = array_keys($array);
        try {
            $items = array_map($callback, $array, $keys);
        } catch (\ArgumentCountError) {
            $items = array_map($callback, $array);
        }
        return array_combine($keys, $items);
    }

    public static function mapWithKeys(array $array, callable $callback): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $assoc = $callback($value, $key);
            foreach ($assoc as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }
        return $result;
    }

    public static function where(array $array, callable $callback): array
    {
        return array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);
    }

    public static function whereNotNull(array $array): array
    {
        return self::where($array, static fn($value) => $value !== null);
    }

    public static function partition(array $array, callable $callback): array
    {
        $passed = [];
        $failed = [];
        foreach ($array as $key => $item) {
            if ($callback($item, $key)) {
                $passed[$key] = $item;
            } else {
                $failed[$key] = $item;
            }
        }
        return [$passed, $failed];
    }

    public static function first(iterable $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            foreach ($array as $item) {
                return $item;
            }
            return value($default);
        }
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }
        return value($default);
    }

    public static function last(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return $array === [] ? value($default) : end($array);
        }
        return self::first(array_reverse($array, true), $callback, $default);
    }
}
