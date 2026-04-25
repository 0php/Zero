<?php

declare(strict_types=1);

namespace Zero\Lib\Http;

use SoapFault;

final class SoapResponse
{
    private mixed $result;
    private array $outputHeaders;
    private ?SoapFault $fault;
    private ?string $lastRequest;
    private ?string $lastRequestHeaders;
    private ?string $lastResponse;
    private ?string $lastResponseHeaders;
    private ?int $statusCode;

    public function __construct(
        mixed $result,
        array $outputHeaders = [],
        ?SoapFault $fault = null,
        ?string $lastRequest = null,
        ?string $lastRequestHeaders = null,
        ?string $lastResponse = null,
        ?string $lastResponseHeaders = null,
        ?int $statusCode = null
    ) {
        $this->result = $result;
        $this->outputHeaders = $outputHeaders;
        $this->fault = $fault;
        $this->lastRequest = $lastRequest;
        $this->lastRequestHeaders = $lastRequestHeaders;
        $this->lastResponse = $lastResponse;
        $this->lastResponseHeaders = $lastResponseHeaders;
        $this->statusCode = $statusCode;
    }

    public function result(): mixed
    {
        return $this->result;
    }

    public function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->result;
        }
        $value = $this->result;
        foreach (explode('.', $key) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } elseif (is_object($value) && isset($value->{$segment})) {
                $value = $value->{$segment};
            } else {
                return $default;
            }
        }
        return $value;
    }

    public function toArray(): array
    {
        return self::normalize($this->result);
    }

    /**
     * @return array<int, mixed>
     */
    public function headers(): array
    {
        return $this->outputHeaders;
    }

    public function fault(): ?SoapFault
    {
        return $this->fault;
    }

    public function successful(): bool
    {
        return $this->fault === null;
    }

    public function failed(): bool
    {
        return $this->fault !== null;
    }

    public function lastRequest(): ?string
    {
        return $this->lastRequest;
    }

    public function lastRequestHeaders(): ?string
    {
        return $this->lastRequestHeaders;
    }

    public function lastResponse(): ?string
    {
        return $this->lastResponse;
    }

    public function lastResponseHeaders(): ?string
    {
        return $this->lastResponseHeaders;
    }

    public function statusCode(): ?int
    {
        return $this->statusCode;
    }

    public function throw(): self
    {
        if ($this->fault !== null) {
            throw $this->fault;
        }
        return $this;
    }

    private static function normalize(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = (array) $value;
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::normalize($v);
            }
            return $out;
        }
        return $value;
    }
}
