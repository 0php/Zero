<?php

declare(strict_types=1);

namespace Zero\Lib\Http\Concerns;

trait InteractsWithJson
{
    protected ?array $jsonPayload = null;

    public function all(): array
    {
        return array_replace_recursive($this->query ?? [], $this->request ?? [], $this->json() ?? []);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->dataGet($this->all(), $key, $default);
    }

    public function has(string $key): bool
    {
        return $this->dataGet($this->all(), $key, null) !== null;
    }

    public function json(?string $key = null, mixed $default = null): ?array
    {
        if ($this->jsonPayload === null) {
            $this->jsonPayload = [];

            if ($this->isJsonRequest()) {
                $decoded = json_decode($this->rawBody ?? '', true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $this->jsonPayload = $decoded;
                }
            }
        }

        if ($key === null) {
            return $this->jsonPayload ?: null;
        }

        return $this->dataGet($this->jsonPayload ?? [], $key, $default);
    }

    protected function isJsonRequest(): bool
    {
        $contentType = strtolower((string) $this->header('content-type', ''));

        return str_contains($contentType, 'application/json') || str_contains($contentType, '+json');
    }

    protected function dataGet(array $target, string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return $target;
        }

        $segments = explode('.', $key);

        foreach ($segments as $segment) {
            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } else {
                return $default;
            }
        }

        return $target;
    }
}
