<?php
namespace Zero\Lib;

use Zero\Lib\Session;

class Http
{
    private static array $defaultHeaders = [];

    public static function setDefaultHeaders(array $headers): void
    {
        self::$defaultHeaders = self::normalizeHeaders($headers);
    }

    public static function addDefaultHeader(string $name, string $value): void
    {
        self::$defaultHeaders[] = $name . ': ' . $value;
    }

    public static function get(string $url, array $query = [], array $headers = [], ?int $timeout = null): object
    {
        return self::request('GET', $url, null, $headers, $timeout, $query);
    }

    public static function post(string $url, mixed $data = null, array $headers = [], ?int $timeout = null, array $query = []): object
    {
        return self::request('POST', $url, $data, $headers, $timeout, $query);
    }

    public static function put(string $url, mixed $data = null, array $headers = [], ?int $timeout = null, array $query = []): object
    {
        return self::request('PUT', $url, $data, $headers, $timeout, $query);
    }

    public static function patch(string $url, mixed $data = null, array $headers = [], ?int $timeout = null, array $query = []): object
    {
        return self::request('PATCH', $url, $data, $headers, $timeout, $query);
    }

    public static function delete(string $url, mixed $data = null, array $headers = [], ?int $timeout = null, array $query = []): object
    {
        return self::request('DELETE', $url, $data, $headers, $timeout, $query);
    }

    private static function request(string $method, string $url, mixed $data = null, array $additionalHeaders = [], ?int $timeout = null, array $query = []): object
    {
        $resolvedUrl = self::resolveUrl($url, $query);
        $headers = self::mergeHeaders($additionalHeaders, $data !== null);

        $options = [
            CURLOPT_URL => $resolvedUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if ($timeout !== null) {
            $timeout = max(1, (int) $timeout);
            $options[CURLOPT_TIMEOUT] = $timeout;
            $options[CURLOPT_CONNECTTIMEOUT] = $timeout;
        }

        if ($data !== null) {
            $options[CURLOPT_POSTFIELDS] = self::preparePayload($data);
            // Ensure headers reflect potential new content-type
            $options[CURLOPT_HTTPHEADER] = self::mergeHeaders($additionalHeaders, true);
        }

        $handle = curl_init();
        curl_setopt_array($handle, $options);

        $responseBody = curl_exec($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_errno($handle) ? curl_error($handle) : null;

        curl_close($handle);

        if ($responseBody === false) {
            $responseBody = null;
        }

        $decoded = null;
        if (is_string($responseBody) && $responseBody !== '') {
            $decoded = json_decode($responseBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $decoded = null;
            }
        }

        if ($statusCode === 0 && $error !== null) {
            $statusCode = 500;
        }

        return (object) [
            'ok' => $error === null && $statusCode >= 200 && $statusCode < 300,
            'status' => $statusCode,
            'body' => $responseBody,
            'json' => $decoded,
            'error' => $error,
        ];
    }

    private static function resolveUrl(string $url, array $query): string
    {
        $absolute = (bool) preg_match('/^https?:\/\//i', $url);

        if (! $absolute) {
            $base = $_ENV['CONFIG']['API_HOST'] ?? '';
            $url = rtrim((string) $base, '/') . '/' . ltrim($url, '/');
        }

        if (! empty($query)) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query($query);
        }

        return $url;
    }

    private static function mergeHeaders(array $additionalHeaders, bool $expectsJsonBody): array
    {
        $headers = self::getBaseHeaders();
        $normalized = self::normalizeHeaders($additionalHeaders);

        foreach ($normalized as $header) {
            $headers[] = $header;
        }

        if ($expectsJsonBody && ! self::hasHeader($headers, 'Content-Type')) {
            $headers[] = 'Content-Type: application/json';
        }

        return $headers;
    }

    private static function preparePayload(mixed $data): string
    {
        if (is_string($data)) {
            return $data;
        }

        if (is_array($data) || is_object($data)) {
            $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded === false ? '' : $encoded;
        }

        return (string) $data;
    }

    private static function getBaseHeaders(): array
    {
        $headers = self::$defaultHeaders;

        if (Session::has('token') && ! self::hasHeader($headers, 'Authorization')) {
            $headers[] = 'Authorization: Bearer ' . Session::get('token');
        }

        return $headers;
    }

    private static function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $key => $value) {
            if (is_int($key)) {
                $normalized[] = $value;
                continue;
            }

            $normalized[] = $key . ': ' . $value;
        }

        return $normalized;
    }

    private static function hasHeader(array $headers, string $name): bool
    {
        foreach ($headers as $header) {
            if (stripos($header, $name . ':') === 0) {
                return true;
            }
        }

        return false;
    }
}
