<?php

declare(strict_types=1);

namespace Zero\Lib\Http;

use Stringable;

final class ClientResponse implements Stringable
{
    private int $status;
    private string $body;
    /** @var array<string, array<int, string>> lower-case header name => values */
    private array $headers;
    private ?string $error;
    private mixed $jsonCache = null;
    private bool $jsonResolved = false;

    public function __construct(int $status, string $body, array $headers = [], ?string $error = null)
    {
        $this->status = $status;
        $this->body = $body;
        $this->error = $error;
        $this->headers = self::normalizeHeaders($headers);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        if (! $this->jsonResolved) {
            $decoded = json_decode($this->body, true);
            $this->jsonCache = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
            $this->jsonResolved = true;
        }

        if ($key === null) {
            return $this->jsonCache;
        }

        if (! is_array($this->jsonCache)) {
            return $default;
        }

        $segments = explode('.', $key);
        $value = $this->jsonCache;
        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }
        return $value;
    }

    public function object(): ?object
    {
        $decoded = json_decode($this->body);
        return json_last_error() === JSON_ERROR_NONE && is_object($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name): ?string
    {
        $key = strtolower($name);
        if (! isset($this->headers[$key])) {
            return null;
        }
        return implode(', ', $this->headers[$key]);
    }

    public function ok(): bool
    {
        return $this->status === 200;
    }

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function redirect(): bool
    {
        return $this->status >= 300 && $this->status < 400;
    }

    public function failed(): bool
    {
        return $this->error !== null || $this->serverError() || $this->clientError();
    }

    public function clientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }

    public function serverError(): bool
    {
        return $this->status >= 500 && $this->status < 600;
    }

    public function unauthorized(): bool
    {
        return $this->status === 401;
    }

    public function forbidden(): bool
    {
        return $this->status === 403;
    }

    public function notFound(): bool
    {
        return $this->status === 404;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    public function throw(): self
    {
        if ($this->failed() || $this->error !== null) {
            throw new \RuntimeException(
                $this->error !== null
                    ? "HTTP request error: {$this->error}"
                    : "HTTP request failed with status {$this->status}",
                $this->status > 0 ? $this->status : 0
            );
        }
        return $this;
    }

    public function toLegacyObject(): object
    {
        return (object) [
            'ok' => $this->successful() && $this->error === null,
            'status' => $this->status,
            'body' => $this->body === '' && $this->error !== null ? null : $this->body,
            'json' => $this->json(),
            'error' => $this->error,
        ];
    }

    public function __toString(): string
    {
        return $this->body;
    }

    /**
     * @param array<int, string>|array<string, string|array<int, string>> $headers
     * @return array<string, array<int, string>>
     */
    private static function normalizeHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $key => $value) {
            if (is_int($key)) {
                if (! is_string($value)) continue;
                if (! str_contains($value, ':')) continue;
                [$name, $val] = explode(':', $value, 2);
                $name = strtolower(trim($name));
                $result[$name][] = trim($val);
            } else {
                $name = strtolower(trim($key));
                if (is_array($value)) {
                    foreach ($value as $v) {
                        $result[$name][] = (string) $v;
                    }
                } else {
                    $result[$name][] = (string) $value;
                }
            }
        }
        return $result;
    }
}
