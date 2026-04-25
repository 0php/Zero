<?php

declare(strict_types=1);

namespace Zero\Lib\Http;

use CURLFile;
use Zero\Lib\Session;

class PendingRequest
{
    /** @var array<string, string> */
    private array $headers = [];
    /** @var array<string, string> */
    private array $cookies = [];
    /** @var array<string, mixed> */
    private array $query = [];
    /** @var array<int, array{name:string, contents:mixed, filename:?string, headers:array<string,string>}> */
    private array $multipart = [];
    /** @var array<int, mixed> */
    private array $curlOptions = [];

    private ?int $timeout = null;
    private ?int $connectTimeout = null;
    private string $bodyFormat = 'json'; // json | form | multipart | body
    private ?string $baseUrl = null;
    private bool $verify = true;
    private int $retries = 0;
    private int $retryDelayMs = 0;
    private ?\Closure $retryWhen = null;
    private bool $debug = false;
    private bool $debugDie = false;
    private bool $throwOnFailure = false;

    public function timeout(int $seconds): self
    {
        $this->timeout = max(1, $seconds);
        return $this;
    }

    public function connectTimeout(int $seconds): self
    {
        $this->connectTimeout = max(1, $seconds);
        return $this;
    }

    public function withHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->headers[$this->normalizeHeaderName((string) $name)] = (string) $value;
        }
        return $this;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$this->normalizeHeaderName($name)] = $value;
        return $this;
    }

    public function acceptJson(): self
    {
        return $this->withHeader('Accept', 'application/json');
    }

    public function accept(string $contentType): self
    {
        return $this->withHeader('Accept', $contentType);
    }

    public function asJson(): self
    {
        $this->bodyFormat = 'json';
        return $this->contentType('application/json');
    }

    public function asForm(): self
    {
        $this->bodyFormat = 'form';
        return $this->contentType('application/x-www-form-urlencoded');
    }

    public function asMultipart(): self
    {
        $this->bodyFormat = 'multipart';
        return $this;
    }

    public function bodyFormat(string $format): self
    {
        $this->bodyFormat = $format;
        return $this;
    }

    public function contentType(string $type): self
    {
        return $this->withHeader('Content-Type', $type);
    }

    public function withToken(string $token, string $type = 'Bearer'): self
    {
        return $this->withHeader('Authorization', trim($type . ' ' . $token));
    }

    public function withBasicAuth(string $username, string $password): self
    {
        return $this->withHeader('Authorization', 'Basic ' . base64_encode($username . ':' . $password));
    }

    public function withQueryParameters(array $query): self
    {
        $this->query = array_replace($this->query, $query);
        return $this;
    }

    public function withCookies(array $cookies): self
    {
        foreach ($cookies as $name => $value) {
            $this->cookies[(string) $name] = (string) $value;
        }
        return $this;
    }

    public function withUserAgent(string $userAgent): self
    {
        return $this->withHeader('User-Agent', $userAgent);
    }

    public function withoutVerifying(): self
    {
        $this->verify = false;
        return $this;
    }

    public function baseUrl(string $url): self
    {
        $this->baseUrl = rtrim($url, '/');
        return $this;
    }

    public function withOptions(array $options): self
    {
        foreach ($options as $key => $value) {
            $this->curlOptions[$key] = $value;
        }
        return $this;
    }

    public function attach(string $name, mixed $contents, ?string $filename = null, array $headers = []): self
    {
        $this->bodyFormat = 'multipart';
        $this->multipart[] = [
            'name' => $name,
            'contents' => $contents,
            'filename' => $filename,
            'headers' => $headers,
        ];
        return $this;
    }

    public function retry(int $times, int $sleepMs = 0, ?callable $when = null): self
    {
        $this->retries = max(0, $times);
        $this->retryDelayMs = max(0, $sleepMs);
        $this->retryWhen = $when !== null ? \Closure::fromCallable($when) : null;
        return $this;
    }

    public function throw(bool $throw = true): self
    {
        $this->throwOnFailure = $throw;
        return $this;
    }

    public function dump(): self
    {
        $this->debug = true;
        return $this;
    }

    public function dd(): self
    {
        $this->debug = true;
        $this->debugDie = true;
        return $this;
    }

    // ----- Terminal methods -----

    public function get(string $url, array $query = []): ClientResponse
    {
        return $this->send('GET', $url, null, $query);
    }

    public function head(string $url, array $query = []): ClientResponse
    {
        return $this->send('HEAD', $url, null, $query);
    }

    public function post(string $url, mixed $data = null, array $query = []): ClientResponse
    {
        return $this->send('POST', $url, $data, $query);
    }

    public function put(string $url, mixed $data = null, array $query = []): ClientResponse
    {
        return $this->send('PUT', $url, $data, $query);
    }

    public function patch(string $url, mixed $data = null, array $query = []): ClientResponse
    {
        return $this->send('PATCH', $url, $data, $query);
    }

    public function delete(string $url, mixed $data = null, array $query = []): ClientResponse
    {
        return $this->send('DELETE', $url, $data, $query);
    }

    public function send(string $method, string $url, mixed $data = null, array $query = []): ClientResponse
    {
        $attempt = 0;
        $maxAttempts = 1 + $this->retries;
        $lastResponse = null;

        while ($attempt < $maxAttempts) {
            $attempt++;
            $lastResponse = $this->dispatch($method, $url, $data, $query);

            if ($lastResponse->successful()) {
                break;
            }

            $shouldRetry = $attempt < $maxAttempts;
            if ($shouldRetry && $this->retryWhen !== null) {
                $shouldRetry = (bool) ($this->retryWhen)($lastResponse, $attempt);
            }
            if (! $shouldRetry) {
                break;
            }
            if ($this->retryDelayMs > 0) {
                usleep($this->retryDelayMs * 1000);
            }
        }

        if ($this->throwOnFailure) {
            $lastResponse->throw();
        }

        return $lastResponse;
    }

    private function dispatch(string $method, string $url, mixed $data, array $extraQuery): ClientResponse
    {
        $resolvedUrl = $this->resolveUrl($url, $extraQuery);
        $headers = $this->buildHeaders();
        $payload = $this->preparePayload($data, $headers);

        $options = [
            CURLOPT_URL => $resolvedUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => $this->verify,
            CURLOPT_SSL_VERIFYHOST => $this->verify ? 2 : 0,
        ];

        if ($this->timeout !== null) {
            $options[CURLOPT_TIMEOUT] = $this->timeout;
        }
        if ($this->connectTimeout !== null) {
            $options[CURLOPT_CONNECTTIMEOUT] = $this->connectTimeout;
        }

        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = $payload;
        }

        if ($this->cookies !== []) {
            $options[CURLOPT_COOKIE] = http_build_query($this->cookies, '', '; ');
        }

        $options[CURLOPT_HTTPHEADER] = $this->headersToWire($headers);

        if (strtoupper($method) === 'HEAD') {
            $options[CURLOPT_NOBODY] = true;
        }

        foreach ($this->curlOptions as $key => $value) {
            $options[$key] = $value;
        }

        if ($this->debug) {
            $this->emitDebug($method, $resolvedUrl, $headers, $payload);
        }

        $handle = curl_init();
        curl_setopt_array($handle, $options);

        $rawResponse = curl_exec($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        $error = curl_errno($handle) ? curl_error($handle) : null;

        curl_close($handle);

        $rawHeaders = '';
        $body = '';

        if (is_string($rawResponse)) {
            $rawHeaders = substr($rawResponse, 0, $headerSize);
            $body = (string) substr($rawResponse, $headerSize);
        }

        if ($statusCode === 0 && $error !== null) {
            $statusCode = 0;
        }

        $parsedHeaders = $this->parseHeaders($rawHeaders);

        return new ClientResponse($statusCode, $body, $parsedHeaders, $error);
    }

    private function resolveUrl(string $url, array $extraQuery): string
    {
        $absolute = (bool) preg_match('/^https?:\/\//i', $url);

        if (! $absolute) {
            $base = $this->baseUrl ?? ($_ENV['CONFIG']['API_HOST'] ?? '');
            if ($base !== '') {
                $url = rtrim((string) $base, '/') . '/' . ltrim($url, '/');
            }
        }

        $combinedQuery = array_replace($this->query, $extraQuery);
        if ($combinedQuery !== []) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query($combinedQuery);
        }

        return $url;
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(): array
    {
        $headers = $this->headers;

        // Auto-attach session token if no explicit Authorization header.
        if (! isset($headers['Authorization']) && class_exists(Session::class) && Session::has('token')) {
            $headers['Authorization'] = 'Bearer ' . Session::get('token');
        }

        return $headers;
    }

    private function preparePayload(mixed $data, array &$headers): mixed
    {
        // Multipart wins if files are attached or asMultipart() was called.
        if ($this->bodyFormat === 'multipart' || $this->multipart !== []) {
            $fields = is_array($data) ? $data : [];
            foreach ($this->multipart as $part) {
                $contents = $part['contents'];
                if (is_string($contents) && is_file($contents)) {
                    $fields[$part['name']] = new CURLFile($contents, null, $part['filename'] ?? basename($contents));
                } elseif (is_string($contents)) {
                    // Inline string contents — write to tmp so curl can attach.
                    $tmp = tempnam(sys_get_temp_dir(), 'zero_http_');
                    if ($tmp !== false) {
                        file_put_contents($tmp, $contents);
                        $fields[$part['name']] = new CURLFile($tmp, null, $part['filename'] ?? $part['name']);
                    }
                } else {
                    $fields[$part['name']] = $contents;
                }
            }
            // libcurl sets multipart Content-Type with boundary automatically.
            unset($headers['Content-Type']);
            return $fields === [] ? null : $fields;
        }

        if ($data === null) {
            return null;
        }

        if ($this->bodyFormat === 'form') {
            if (! isset($headers['Content-Type'])) {
                $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            }
            return is_array($data) ? http_build_query($data) : (string) $data;
        }

        if ($this->bodyFormat === 'body') {
            return is_string($data) ? $data : (string) $data;
        }

        // Default: JSON
        if (! isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }
        if (is_string($data)) {
            return $data;
        }
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? '' : $encoded;
    }

    /**
     * @param array<string, string> $headers
     * @return array<int, string>
     */
    private function headersToWire(array $headers): array
    {
        $wire = [];
        foreach ($headers as $name => $value) {
            $wire[] = $name . ': ' . $value;
        }
        return $wire;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function parseHeaders(string $raw): array
    {
        $headers = [];
        $blocks = preg_split('/\r?\n\r?\n/', trim($raw)) ?: [];
        // Take the last block (handles redirects/100-continue intermediate responses).
        $last = end($blocks);
        if ($last === false) {
            return $headers;
        }
        $lines = preg_split('/\r?\n/', $last) ?: [];
        foreach ($lines as $line) {
            if (! str_contains($line, ':')) continue;
            [$name, $value] = explode(':', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if ($name === '') continue;
            $headers[$name][] = $value;
        }
        return $headers;
    }

    private function normalizeHeaderName(string $name): string
    {
        $parts = explode('-', $name);
        return implode('-', array_map(static fn($p) => ucfirst(strtolower($p)), $parts));
    }

    private function emitDebug(string $method, string $url, array $headers, mixed $payload): void
    {
        $lines = [
            '> ' . strtoupper($method) . ' ' . $url,
        ];
        foreach ($headers as $name => $value) {
            $lines[] = '> ' . $name . ': ' . $value;
        }
        if ($payload !== null) {
            $lines[] = '>';
            $lines[] = '> ' . (is_string($payload) ? $payload : print_r($payload, true));
        }
        $output = implode("\n", $lines) . "\n";
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $output);
        } else {
            echo '<pre>' . htmlspecialchars($output) . '</pre>';
        }
        if ($this->debugDie) {
            exit(1);
        }
    }
}
