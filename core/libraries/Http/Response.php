<?php

declare(strict_types=1);

namespace Zero\Lib\Http;

use JsonSerializable;
use RuntimeException;
use Throwable;
use Traversable;
use Zero\Lib\Http\Traits\BuildsResponse;
use Zero\Lib\Http\Traits\ManagesHeaders;
use Zero\Lib\Http\Traits\StreamsResponse;
use Zero\Lib\Router;

class Response
{
    use BuildsResponse;
    use ManagesHeaders;
    use StreamsResponse;

    protected int $status = 200;
    protected string $content = '';

    public static function make(mixed $content = '', int $status = 200, array $headers = []): static
    {
        return (new static())->setStatus($status)
            ->setContent((string) $content)
            ->withHeaders($headers)
            ->ensureContentType('text/html; charset=UTF-8');
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): static
    {
        $payload = static::encodeJson($data);

        return (new static())->setStatus($status)
            ->setContent($payload)
            ->withHeaders($headers)
            ->header('Content-Type', 'application/json; charset=UTF-8');
    }

    public static function text(string $text, int $status = 200, array $headers = []): static
    {
        return (new static())->setStatus($status)
            ->setContent($text)
            ->withHeaders($headers)
            ->ensureContentType('text/plain; charset=UTF-8');
    }

    public static function html(string $html, int $status = 200, array $headers = []): static
    {
        return (new static())->setStatus($status)
            ->setContent($html)
            ->withHeaders($headers)
            ->ensureContentType('text/html; charset=UTF-8');
    }

    public static function xml(string $xml, int $status = 200, array $headers = []): static
    {
        return (new static())->setStatus($status)
            ->setContent($xml)
            ->withHeaders($headers)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public static function api(string $status, mixed $payload = null, int $statusCode = 200, array $headers = []): static
    {
        $body = [
            'code' => $statusCode,
            'status' => $status,
            'data' => $statusCode >= 400 ? null : $payload,
            'error' => $statusCode >= 400 ? $payload : null,
        ];

        return static::json($body, $statusCode, $headers);
    }

    public static function redirect(string $location, int $status = 302, array $headers = []): static
    {
        return static::make('', $status, $headers)->header('Location', $location);
    }

    public static function redirectRoute(string $name, array $parameters = [], bool $absolute = true, int $status = 302, array $headers = []): static
    {
        $url = Router::route($name, $parameters, $absolute);

        return static::redirect($url, $status, $headers);
    }

    public static function redirectBack(string $fallback = '/', int $status = 302, array $headers = []): static
    {
        $referer = Request::instance()->header('referer', $fallback);
        $target = $referer !== null && $referer !== '' ? $referer : $fallback;

        return static::redirect($target, $status, $headers);
    }

    public static function stream(callable|string $stream, int $status = 200, array $headers = [], string $contentType = 'text/event-stream'): static
    {
        $response = new static();
        $response->setStatus($status);
        $response->stream($stream);
        $response->withHeaders($headers);
        $response->ensureContentType($contentType);

        return $response;
    }

    public static function file(string $path, array $headers = [], ?string $name = null, string $disposition = 'inline'): static
    {
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('File [%s] does not exist.', $path));
        }

        $mime = mime_content_type($path) ?: 'application/octet-stream';
        $size = filesize($path);
        $name ??= basename($path);
        $encodedName = addcslashes($name, '"\\');

        $baseHeaders = [
            'Content-Type' => $mime,
            'Content-Disposition' => sprintf('%s; filename="%s"', $disposition, $encodedName),
        ];

        if ($size !== false) {
            $baseHeaders['Content-Length'] = (string) $size;
        }

        $merged = array_merge($baseHeaders, $headers);

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read file [%s].', $path));
        }

        return (new static())
            ->setStatus(200)
            ->withHeaders($merged)
            ->setContent($contents);
    }

    public static function noContent(int $status = 204, array $headers = []): static
    {
        return (new static())->setStatus($status)->withHeaders($headers)->setContent('');
    }

    public static function resolve(mixed $value): static
    {
        if ($value instanceof static) {
            return $value;
        }

        if ($value instanceof self) {
            return static::fromBase($value);
        }

        if ($value === null) {
            return static::noContent();
        }

        if (is_array($value) || $value instanceof JsonSerializable || $value instanceof Traversable || is_object($value)) {
            return static::json(static::normalizeIterable($value));
        }

        if ($value instanceof Throwable) {
            return static::json([
                'message' => $value->getMessage(),
                'code' => $value->getCode(),
            ], 500);
        }

        if (is_bool($value)) {
            return static::json($value);
        }

        return static::html((string) $value);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->getHeaders() as $name => $value) {
            header($name . ': ' . $value, true);
        }

        $this->outputContent();
    }

    protected static function fromBase(self $response): static
    {
        $clone = new static();
        $clone->setStatus($response->getStatus());
        $clone->setContent($response->getContent());
        $clone->withHeaders($response->getHeaders());

        if ($response->streaming) {
            $clone->streaming = true;
            $clone->streamHandler = $response->streamHandler;
        }

        return $clone;
    }

    protected static function encodeJson(mixed $data): string
    {
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            throw new RuntimeException('Unable to encode data to JSON: ' . json_last_error_msg());
        }

        return $encoded;
    }

    protected static function normalizeIterable(mixed $value): mixed
    {
        if ($value instanceof JsonSerializable) {
            return $value->jsonSerialize();
        }

        if ($value instanceof Traversable) {
            return iterator_to_array($value);
        }

        if (is_object($value)) {
            return get_object_vars($value);
        }

        return $value;
    }
}
