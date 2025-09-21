<?php

declare(strict_types=1);

namespace Zero\Lib\Http\Concerns;

use InvalidArgumentException;

trait InteractsWithServer
{
    protected array $server;
    protected array $query;
    protected array $request;
    protected array $files;
    protected string $rawBody;
    /** @var array<string, mixed> */
    protected array $attributes = [];

    protected static ?self $instance = null;

    public function __construct(
        array $query = [],
        array $request = [],
        array $files = [],
        array $cookies = [],
        array $server = [],
        string $rawBody = ''
    ) {
        $this->query = $query;
        $this->request = $request;
        $this->files = $files;
        $this->server = $server;
        $this->rawBody = $rawBody;
        $this->initialiseHeaders($server);
        $this->initialiseCookies($cookies);
    }

    public static function capture(): self
    {
        if (static::$instance instanceof self) {
            return static::$instance;
        }

        $rawBody = file_get_contents('php://input');
        static::$instance = new static(
            $_GET,
            $_POST,
            $_FILES,
            $_COOKIE,
            $_SERVER,
            $rawBody === false ? '' : $rawBody
        );

        return static::$instance;
    }

    public static function instance(): self
    {
        return static::$instance ?? static::capture();
    }

    public static function replace(array $overrides): self
    {
        $defaults = [
            'query' => [],
            'request' => [],
            'files' => [],
            'cookies' => [],
            'server' => [],
            'body' => '',
            'attributes' => [],
        ];

        $data = array_merge($defaults, $overrides);

        static::$instance = new static(
            $data['query'],
            $data['request'],
            $data['files'],
            $data['cookies'],
            $data['server'],
            $data['body']
        );

        if (! empty($data['attributes']) && is_array($data['attributes'])) {
            static::$instance->attributes = $data['attributes'];
        }

        return static::$instance;
    }

    public static function set(string $key, mixed $value): void
    {
        $instance = static::instance();
        $instance->attributes[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::instance()->attribute($key, $default);
    }

    public static function __callStatic(string $name, array $arguments): mixed
    {
        $instance = static::instance();

        if (!method_exists($instance, $name)) {
            throw new InvalidArgumentException("Method {$name} does not exist on Request");
        }

        return $instance->$name(...$arguments);
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    public function files(): array
    {
        return $this->files;
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        return trim($path, '/') ?: '/';
    }

    public function uri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    public function root(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = $this->server['HTTP_HOST'] ?? ($this->server['SERVER_NAME'] ?? 'localhost');

        return $scheme . '://' . $host;
    }

    public function fullUrl(): string
    {
        $query = $this->server['QUERY_STRING'] ?? '';
        $uri = rtrim($this->root(), '/') . '/' . ltrim($this->path(), '/');

        return $query ? $uri . '?' . $query : $uri;
    }

    public function getContent(): string
    {
        return $this->rawBody;
    }

    public function ip(): ?string
    {
        $keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

        foreach ($keys as $key) {
            if (!empty($this->server[$key])) {
                $value = $this->server[$key];
                if (is_string($value)) {
                    return explode(',', $value)[0];
                }
            }
        }

        return null;
    }

    protected function isSecure(): bool
    {
        $https = $this->server['HTTPS'] ?? null;

        if ($https && strtolower((string) $https) !== 'off') {
            return true;
        }

        return ($this->server['SERVER_PORT'] ?? null) === 443;
    }
}
