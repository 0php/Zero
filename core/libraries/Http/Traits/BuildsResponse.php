<?php

declare(strict_types=1);

namespace Zero\Lib\Http\Traits;

trait BuildsResponse
{
    protected int $status = 200;
    protected string $content = '';

    public function status(int $code): static
    {
        return $this->setStatus($code);
    }

    public function header(string $name, string $value): static
    {
        $normalized = strtolower($name);

        if (isset($this->headerNames[$normalized])) {
            $originalName = $this->headerNames[$normalized];
            $this->headers[$originalName] = $value;
        } else {
            $this->headerNames[$normalized] = $name;
            $this->headers[$name] = $value;
        }

        return $this;
    }

    public function withHeaders(array $headers): static
    {
        foreach ($headers as $name => $value) {
            $this->header($name, (string) $value);
        }

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function __invoke(): void
    {
        $this->send();
    }

    public function __toString(): string
    {
        return $this->content;
    }

    protected function setStatus(int $status): static
    {
        $this->status = max(100, min($status, 599));

        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    protected function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    protected function ensureContentType(string $default): static
    {
        if (! $this->hasHeader('Content-Type')) {
            $this->header('Content-Type', $default);
        }

        return $this;
    }
}
