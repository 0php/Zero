<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Arr;

use ArrayAccess;

trait Access
{
    public static function accessible(mixed $value): bool
    {
        return is_array($value) || $value instanceof ArrayAccess;
    }

    public static function add(array $array, string|int $key, mixed $value): array
    {
        if (self::get($array, $key) === null) {
            self::set($array, $key, $value);
        }
        return $array;
    }

    public static function exists(array|ArrayAccess $array, string|int $key): bool
    {
        if ($array instanceof ArrayAccess) {
            return $array->offsetExists($key);
        }
        return array_key_exists($key, $array);
    }

    public static function get(array|ArrayAccess|null $array, string|int|null $key = null, mixed $default = null): mixed
    {
        if (! self::accessible($array)) {
            return value($default);
        }
        if ($key === null) {
            return $array;
        }
        if (self::exists($array, $key)) {
            return $array[$key];
        }
        if (! is_string($key) || ! str_contains($key, '.')) {
            return $array[$key] ?? value($default);
        }
        foreach (explode('.', $key) as $segment) {
            if (self::accessible($array) && self::exists($array, $segment)) {
                $array = $array[$segment];
            } else {
                return value($default);
            }
        }
        return $array;
    }

    public static function set(array &$array, string|int|null $key, mixed $value): array
    {
        if ($key === null) {
            return $array = $value;
        }
        $keys = explode('.', (string) $key);
        $current = &$array;
        while (count($keys) > 1) {
            $segment = array_shift($keys);
            if (! isset($current[$segment]) || ! is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }
        $current[array_shift($keys)] = $value;
        return $array;
    }

    public static function has(array|ArrayAccess $array, string|array $keys): bool
    {
        $keys = (array) $keys;
        if ($array === [] || $keys === []) {
            return false;
        }
        foreach ($keys as $key) {
            $sub = $array;
            if (self::exists($array, $key)) {
                continue;
            }
            foreach (explode('.', (string) $key) as $segment) {
                if (self::accessible($sub) && self::exists($sub, $segment)) {
                    $sub = $sub[$segment];
                } else {
                    return false;
                }
            }
        }
        return true;
    }

    public static function hasAny(array|ArrayAccess $array, string|array $keys): bool
    {
        if ($array === []) {
            return false;
        }
        foreach ((array) $keys as $key) {
            if (self::has($array, $key)) {
                return true;
            }
        }
        return false;
    }

    public static function forget(array &$array, array|string|int $keys): void
    {
        $original = &$array;
        $keys = (array) $keys;
        if ($keys === []) {
            return;
        }
        foreach ($keys as $key) {
            if (self::exists($array, $key)) {
                unset($array[$key]);
                continue;
            }
            $parts = explode('.', (string) $key);
            $array = &$original;
            while (count($parts) > 1) {
                $part = array_shift($parts);
                if (isset($array[$part]) && is_array($array[$part])) {
                    $array = &$array[$part];
                } else {
                    continue 2;
                }
            }
            unset($array[array_shift($parts)]);
        }
    }

    public static function pull(array &$array, string|int $key, mixed $default = null): mixed
    {
        $value = self::get($array, $key, $default);
        self::forget($array, $key);
        return $value;
    }
}
