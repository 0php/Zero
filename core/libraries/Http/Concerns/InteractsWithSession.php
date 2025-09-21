<?php

declare(strict_types=1);

namespace Zero\Lib\Http\Concerns;

trait InteractsWithSession
{
    public function session(?string $key = null, mixed $default = null): mixed
    {
        if (!isset($GLOBALS['_SESSION'])) {
            return $key === null ? [] : $default;
        }

        if ($key === null) {
            return $_SESSION;
        }

        return $_SESSION[$key] ?? $default;
    }
}
