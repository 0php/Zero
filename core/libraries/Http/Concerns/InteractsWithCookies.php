<?php

declare(strict_types=1);

namespace Zero\Lib\Http\Concerns;

trait InteractsWithCookies
{
    protected array $cookies = [];

    protected function initialiseCookies(array $cookies): void
    {
        $this->cookies = $cookies;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function cookies(): array
    {
        return $this->cookies;
    }
}
