<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Arr;

trait Internal
{
    /**
     * Read a nested value from an array/object using a key path (segments or dot string).
     */
    protected static function dataGet(mixed $target, array|string|int|null $key): mixed
    {
        if ($key === null) {
            return $target;
        }
        $segments = is_array($key) ? $key : [$key];

        foreach ($segments as $segment) {
            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return null;
            }
        }

        return $target;
    }
}
