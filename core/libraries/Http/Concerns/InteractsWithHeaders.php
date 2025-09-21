<?php

declare(strict_types=1);

namespace Zero\Lib\Http\Concerns;

trait InteractsWithHeaders
{
    protected array $headers = [];

    protected function initialiseHeaders(array $server): void
    {
        $this->headers = $this->detectHeaders($server);
    }

    public function header(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->headers;
        }

        $normalized = strtolower($key);

        return $this->headers[$normalized] ?? $default;
    }

    public function expectsJson(): bool
    {
        return $this->wantsJson() || strtolower((string) $this->header('x-requested-with', '')) === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        $accept = $this->header('accept', '');

        return str_contains(strtolower($accept), 'application/json');
    }

    protected function detectHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$header] = (string) $value;
            }
        }

        if (isset($server['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $server['CONTENT_TYPE'];
        }

        if (isset($server['CONTENT_LENGTH'])) {
            $headers['content-length'] = (string) $server['CONTENT_LENGTH'];
        }

        return $headers;
    }
}
