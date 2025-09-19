<?php

declare(strict_types=1);

namespace Zero\Lib\Http\Traits;

trait ManagesHeaders
{
    protected array $headers = [];
    protected array $headerNames = [];

    public function hasHeader(string $name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    public function getHeaders(): array
    {
        $headers = [];

        foreach ($this->headerNames as $lower => $original) {
            $headers[$original] = $this->headers[$original];
        }

        return $headers;
    }
}
