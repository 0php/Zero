<?php

declare(strict_types=1);

namespace Zero\Lib;

use Zero\Lib\Http\PendingRequest;
use Zero\Lib\Http\SoapRequest;

/**
 * Http facade.
 *
 * Two ways to use it:
 *
 * 1. Legacy static (back-compatible) — returns a plain stdClass:
 *      $r = Http::get($url, $query, $headers, $timeout);
 *      $r->ok; $r->status; $r->body; $r->json; $r->error;
 *
 * 2. Fluent (preferred) — returns a Zero\Lib\Http\ClientResponse:
 *      $r = Http::timeout(10)->acceptJson()->withToken($t)->get($url);
 *      $r->successful(); $r->status(); $r->body(); $r->json(); $r->headers();
 */
class Http
{
    /** @var array<string, string> */
    private static array $defaultHeaders = [];

    // ----- Legacy global default-header accessors (kept for BC) -----

    public static function setDefaultHeaders(array $headers): void
    {
        self::$defaultHeaders = [];
        foreach ($headers as $key => $value) {
            if (is_int($key)) {
                if (is_string($value) && str_contains($value, ':')) {
                    [$n, $v] = explode(':', $value, 2);
                    self::$defaultHeaders[trim($n)] = trim($v);
                }
            } else {
                self::$defaultHeaders[(string) $key] = (string) $value;
            }
        }
    }

    public static function addDefaultHeader(string $name, string $value): void
    {
        self::$defaultHeaders[$name] = $value;
    }

    // ----- Fluent entry points -----

    public static function timeout(int $seconds): PendingRequest
    {
        return self::pending()->timeout($seconds);
    }

    public static function connectTimeout(int $seconds): PendingRequest
    {
        return self::pending()->connectTimeout($seconds);
    }

    public static function withHeaders(array $headers): PendingRequest
    {
        return self::pending()->withHeaders($headers);
    }

    public static function withHeader(string $name, string $value): PendingRequest
    {
        return self::pending()->withHeader($name, $value);
    }

    public static function acceptJson(): PendingRequest
    {
        return self::pending()->acceptJson();
    }

    public static function accept(string $contentType): PendingRequest
    {
        return self::pending()->accept($contentType);
    }

    public static function asJson(): PendingRequest
    {
        return self::pending()->asJson();
    }

    public static function asForm(): PendingRequest
    {
        return self::pending()->asForm();
    }

    public static function asMultipart(): PendingRequest
    {
        return self::pending()->asMultipart();
    }

    public static function bodyFormat(string $format): PendingRequest
    {
        return self::pending()->bodyFormat($format);
    }

    public static function contentType(string $type): PendingRequest
    {
        return self::pending()->contentType($type);
    }

    public static function withToken(string $token, string $type = 'Bearer'): PendingRequest
    {
        return self::pending()->withToken($token, $type);
    }

    public static function withBasicAuth(string $username, string $password): PendingRequest
    {
        return self::pending()->withBasicAuth($username, $password);
    }

    public static function withQueryParameters(array $query): PendingRequest
    {
        return self::pending()->withQueryParameters($query);
    }

    public static function withCookies(array $cookies): PendingRequest
    {
        return self::pending()->withCookies($cookies);
    }

    public static function withUserAgent(string $userAgent): PendingRequest
    {
        return self::pending()->withUserAgent($userAgent);
    }

    public static function withoutVerifying(): PendingRequest
    {
        return self::pending()->withoutVerifying();
    }

    public static function baseUrl(string $url): PendingRequest
    {
        return self::pending()->baseUrl($url);
    }

    public static function withOptions(array $options): PendingRequest
    {
        return self::pending()->withOptions($options);
    }

    public static function attach(string $name, mixed $contents, ?string $filename = null, array $headers = []): PendingRequest
    {
        return self::pending()->attach($name, $contents, $filename, $headers);
    }

    public static function retry(int $times, int $sleepMs = 0, ?callable $when = null): PendingRequest
    {
        return self::pending()->retry($times, $sleepMs, $when);
    }

    public static function dump(): PendingRequest
    {
        return self::pending()->dump();
    }

    public static function dd(): PendingRequest
    {
        return self::pending()->dd();
    }

    /**
     * Build a fluent SOAP request.
     *
     * Examples:
     *   Http::soap('https://api.example.com/service?wsdl')->call('GetRates', [...]);
     *   Http::soap()->endpoint($url)->uri('urn:Foo')->action('urn:Foo#Bar')->call('Bar', [...]);
     */
    public static function soap(?string $wsdl = null): SoapRequest
    {
        return new SoapRequest($wsdl);
    }

    // ----- Legacy verb statics (back-compatible signatures + return type) -----

    public static function get(string $url, array $query = [], array $headers = [], ?int $timeout = null): object
    {
        return self::legacyVerb('GET', $url, null, $headers, $timeout, $query);
    }

    public static function post(string $url, mixed $data = null, array $headers = [], ?int $timeout = null, array $query = []): object
    {
        return self::legacyVerb('POST', $url, $data, $headers, $timeout, $query);
    }

    public static function put(string $url, mixed $data = null, array $headers = [], ?int $timeout = null, array $query = []): object
    {
        return self::legacyVerb('PUT', $url, $data, $headers, $timeout, $query);
    }

    public static function patch(string $url, mixed $data = null, array $headers = [], ?int $timeout = null, array $query = []): object
    {
        return self::legacyVerb('PATCH', $url, $data, $headers, $timeout, $query);
    }

    public static function delete(string $url, mixed $data = null, array $headers = [], ?int $timeout = null, array $query = []): object
    {
        return self::legacyVerb('DELETE', $url, $data, $headers, $timeout, $query);
    }

    private static function legacyVerb(string $method, string $url, mixed $data, array $headers, ?int $timeout, array $query): object
    {
        $request = self::pending();
        if ($headers !== []) {
            $request->withHeaders(self::flattenLegacyHeaders($headers));
        }
        if ($timeout !== null) {
            $request->timeout($timeout);
        }
        $response = $request->send($method, $url, $data, $query);
        return $response->toLegacyObject();
    }

    /**
     * Build a fresh PendingRequest seeded with global default headers.
     */
    private static function pending(): PendingRequest
    {
        $request = new PendingRequest();
        if (self::$defaultHeaders !== []) {
            $request->withHeaders(self::$defaultHeaders);
        }
        return $request;
    }

    /**
     * Convert legacy header arrays (mixed numeric "Name: Value" lines and assoc) into associative.
     *
     * @param array<int|string, string> $headers
     * @return array<string, string>
     */
    private static function flattenLegacyHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $key => $value) {
            if (is_int($key)) {
                if (is_string($value) && str_contains($value, ':')) {
                    [$n, $v] = explode(':', $value, 2);
                    $result[trim($n)] = trim($v);
                }
            } else {
                $result[(string) $key] = (string) $value;
            }
        }
        return $result;
    }
}
