<?php

declare(strict_types=1);

namespace Zero\Lib\Http\Concerns;

use InvalidArgumentException;
use Zero\Lib\Http\UploadedFile;

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
        $this->files = $this->normalizeFiles($files);
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

    protected function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFile) {
                $normalized[$key] = $value;
                continue;
            }

            if (is_array($value) && isset($value['name'])) {
                $mapped = $this->mapUploadedFileArray($value);

                if ($mapped !== null) {
                    $normalized[$key] = $mapped;
                }
                continue;
            }

            if (is_array($value)) {
                $nested = $this->normalizeFiles($value);

                if ($nested !== []) {
                    $normalized[$key] = $nested;
                }
            }
        }

        return $normalized;
    }

    protected function mapUploadedFileArray(array $file): mixed
    {
        $names = $file['name'] ?? null;

        if (is_array($names)) {
            $normalized = [];

            foreach ($names as $index => $name) {
                $item = $this->mapUploadedFileArray([
                    'name' => $name,
                    'type' => $file['type'][$index] ?? null,
                    'tmp_name' => $file['tmp_name'][$index] ?? null,
                    'error' => $file['error'][$index] ?? null,
                    'size' => $file['size'][$index] ?? null,
                ]);

                if ($item !== null) {
                    $normalized[$index] = $item;
                }
            }

            return array_values($normalized);
        }

        $tmp = $file['tmp_name'] ?? null;

        if ($tmp === null || $tmp === '') {
            return null;
        }

        return new UploadedFile(
            (string) $tmp,
            (string) ($file['name'] ?? ''),
            (string) ($file['type'] ?? ''),
            (int) ($file['size'] ?? 0),
            (int) ($file['error'] ?? UPLOAD_ERR_OK)
        );
    }

    protected function dataGet(array $target, string $key, mixed $default = null): mixed
    {
        if ($key === '' || $key === null) {
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

    public function file(string $key, mixed $default = null): mixed
    {
        $value = $this->dataGet($this->files, $key, $default);

        if ($value instanceof UploadedFile) {
            return $value;
        }

        if (is_array($value)) {
            return $value === [] ? $default : $value;
        }

        return $default;
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

    /**
     * The client IP address. Forwarding headers (X-Forwarded-For) are only honoured when the
     * direct peer (REMOTE_ADDR) is listed in TRUSTED_PROXIES (comma separated IPs/CIDRs, or "*").
     */
    public function ip(): ?string
    {
        $remote = $this->server['REMOTE_ADDR'] ?? null;
        $remote = is_string($remote) && trim($remote) !== '' ? trim($remote) : null;

        if ($remote === null) {
            return null;
        }

        $trusted = $this->trustedProxies();

        if ($trusted === [] || !$this->ipInList($remote, $trusted)) {
            return $remote;
        }

        $forwarded = $this->server['HTTP_X_FORWARDED_FOR'] ?? '';
        $chain = is_string($forwarded) ? array_map('trim', explode(',', $forwarded)) : [];

        // Walk from the closest hop outwards and return the first address that is not a trusted proxy.
        foreach (array_reverse($chain) as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP) === false) {
                break;
            }

            if (!$this->ipInList($candidate, $trusted)) {
                return $candidate;
            }

            $remote = $candidate;
        }

        return $remote;
    }

    /**
     * @return string[]
     */
    protected function trustedProxies(): array
    {
        $value = function_exists('env') ? env('TRUSTED_PROXIES', '') : (getenv('TRUSTED_PROXIES') ?: '');

        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    }

    /**
     * @param string[] $list
     */
    protected function ipInList(string $ip, array $list): bool
    {
        foreach ($list as $entry) {
            if ($entry === '*' || $entry === $ip || $this->ipInCidr($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    protected function ipInCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return false;
        }

        [$subnet, $bits] = explode('/', $cidr, 2);
        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin) || !ctype_digit($bits)) {
            return false;
        }

        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($bits > strlen($ipBin) * 8) {
            return false;
        }

        if (strncmp($ipBin, $subnetBin, $bytes) !== 0) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = chr((0xFF << (8 - $remainder)) & 0xFF);

        return (ord($ipBin[$bytes]) & ord($mask)) === (ord($subnetBin[$bytes]) & ord($mask));
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
