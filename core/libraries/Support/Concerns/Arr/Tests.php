<?php

declare(strict_types=1);

namespace Zero\Lib\Support\Concerns\Arr;

trait Tests
{
    public static function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }

    public static function isList(array $array): bool
    {
        return array_is_list($array);
    }

    public static function join(array $array, string $glue, string $finalGlue = ''): string
    {
        if ($finalGlue === '') {
            return implode($glue, $array);
        }
        if (count($array) < 2) {
            return implode($glue, $array);
        }
        $finalItem = array_pop($array);
        return implode($glue, $array) . $finalGlue . $finalItem;
    }

    public static function query(array $array): string
    {
        return http_build_query($array, '', '&', PHP_QUERY_RFC3986);
    }
}
