<?php

declare(strict_types=1);

namespace Zero\Lib\Storage\Adapters;

use InvalidArgumentException;
use RuntimeException;

/**
 * Lightweight S3-compatible client (Signature V4) without external dependencies.
 *
 * Supports custom endpoints (Wasabi, Tebi, MinIO, etc.), ACLs, multipart uploads,
 * presigned URLs/POST, folder listings, retries, and simple streaming helpers.
 */
class S3Adapter
{
    private string $accessKey;
    private string $secretKey;
    private string $region;
    private string $signingRegion;
    private string $bucket;
    private ?string $sessionToken;
    private int $timeout;
    private int $maxRetries = 4;
    private ?string $endpoint = null;
    private bool $pathStyle = true;
    private ?int $endpointPort = null;
    private string $endpointScheme = 'https';
    private ?string $defaultAcl = null;
    private string $signatureVersion;
    private bool $autoDetectSignature = false;

    public function __construct(
        string $accessKey,
        string $secretKey,
        string $region,
        string $bucket,
        ?string $sessionToken = null,
        int $timeoutSeconds = 60,
        ?string $signingRegion = null,
        ?string $signatureVersion = null
    ) {
        $this->accessKey    = trim($accessKey);
        $this->secretKey    = trim($secretKey);
        $this->region       = trim($region);
        $this->bucket       = trim($bucket);
        $this->sessionToken = $sessionToken;
        $this->timeout      = $timeoutSeconds;
        if ($signingRegion !== null) {
            $signingRegion = trim($signingRegion);
        }

        $this->signingRegion = $signingRegion !== null && $signingRegion !== ''
            ? $signingRegion
            : ($this->region !== '' ? $this->region : 'us-east-1');

        $version = strtolower($signatureVersion ?? 'auto');

        if ($version === 'auto' || $version === '') {
            $this->autoDetectSignature = true;
            $this->signatureVersion = 'v4';
        } elseif (in_array($version, ['v2', 'v4'], true)) {
            $this->signatureVersion = $version;
        } else {
            $this->signatureVersion = 'v4';
        }
    }

    public function setEndpoint(string $endpoint, bool $pathStyle = true): void
    {
        $parts = parse_url($endpoint);
        if (!$parts || !isset($parts['host'])) {
            throw new InvalidArgumentException("Invalid endpoint URL: {$endpoint}");
        }
        $this->endpoint       = rtrim($endpoint, '/');
        $this->pathStyle      = $pathStyle;
        $this->endpointScheme = $parts['scheme'] ?? 'https';
        $this->endpointPort   = isset($parts['port']) ? (int) $parts['port'] : null;
    }

    public function setMaxRetries(int $n): void
    {
        $this->maxRetries = max(0, $n);
    }

    public function setDefaultAcl(?string $acl): void
    {
        if ($acl === null) {
            $this->defaultAcl = null;
            return;
        }

        $allowed = [
            'private',
            'public-read',
            'public-read-write',
            'authenticated-read',
            'bucket-owner-read',
            'bucket-owner-full-control',
            'aws-exec-read',
            'log-delivery-write',
        ];

        if (! in_array($acl, $allowed, true)) {
            throw new InvalidArgumentException("Invalid canned ACL: {$acl}");
        }

        $this->defaultAcl = $acl;
    }

    public function putObject(string $key, string $body, array $headers = []): int
    {
        if ($this->defaultAcl && ! isset($headers['x-amz-acl']) && ! isset($headers['X-Amz-Acl'])) {
            $headers['x-amz-acl'] = $this->defaultAcl;
        }

        $headers['Content-Length'] = (string) strlen($body);
        $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/octet-stream';

        [$status, $response] = $this->request('PUT', $key, $headers, $body);

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf(
                'Unable to upload object [%s] (status %d): %s',
                $key,
                $status,
                trim($response)
            ));
        }

        return $status;
    }

    public function putObjectFromFile(string $key, string $filepath, array $headers = []): int
    {
        if (! is_readable($filepath)) {
            throw new RuntimeException("File not readable: {$filepath}");
        }

        $data = file_get_contents($filepath);

        if ($data === false) {
            throw new RuntimeException("Failed reading: {$filepath}");
        }

        $headers['Content-Type'] ??= $this->guessContentType($filepath);

        return $this->putObject($key, $data, $headers);
    }

    public function downloadObject(string $key, string $destinationPath): void
    {
        [, $body] = $this->request('GET', $key, [], null);

        $directory = dirname($destinationPath);

        if ($directory !== '' && !is_dir($directory)) {
            if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException(sprintf('Unable to create directory [%s].', $directory));
            }
        }

        if (file_put_contents($destinationPath, $body) === false) {
            throw new RuntimeException(sprintf('Unable to write [%s].', $destinationPath));
        }
    }

    public function streamObject(string $key)
    {
        $uri = $this->createPresignedUrl('GET', $key, max(60, $this->timeout));

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
            ],
        ]);

        return fopen($uri, 'rb', false, $context);
    }

    public function getObject(string $key): array
    {
        return $this->request('GET', $key, [], null);
    }

    public function headObject(string $key): array
    {
        return $this->request('HEAD', $key, [], null);
    }

    public function deleteObject(string $key): int
    {
        [$status] = $this->request('DELETE', $key, [], null);

        return $status;
    }

    public function listAllObjects(string $prefix = ''): \Generator
    {
        $token = null;

        do {
            [$status, $body] = $this->listObjectsV2($prefix, $token);

            if ($status !== 200) {
                break;
            }

            $parsed = $this->parseListV2Xml($body);

            foreach ($parsed['Contents'] ?? [] as $content) {
                yield $content;
            }

            $token = $parsed['NextContinuationToken'] ?? null;
        } while ($token !== null);
    }

    public function createPresignedUrl(string $method, string $key, int $expiresSeconds, array $headers = []): string
    {
        if ($this->signatureVersion === 'v2') {
            return $this->createPresignedUrlV2($method, $key, $expiresSeconds, $headers);
        }

        $uri = $this->buildRequestUri($key);
        $now = gmdate('Ymd\THis\Z');
        $datestamp = substr($now, 0, 8);

        $preparedHeaders = array_merge($headers, [
            'host' => $this->hostFor($key),
        ]);

        $credential = sprintf('%s/%s/%s/s3/aws4_request', $this->accessKey, $datestamp, $this->signingRegion);
        $signedHeaders = $this->canonicalSignedHeaders($preparedHeaders);
        $scope = sprintf('%s/%s/s3/aws4_request', $datestamp, $this->signingRegion);

        $query = [
            'X-Amz-Algorithm'  => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => rawurlencode($credential),
            'X-Amz-Date'       => $now,
            'X-Amz-Expires'    => (string) $expiresSeconds,
            'X-Amz-SignedHeaders' => $signedHeaders,
        ];

        if ($this->sessionToken !== null) {
            $query['X-Amz-Security-Token'] = rawurlencode($this->sessionToken);
        }

        $canonicalQuery = $this->canonicalQueryString($query);
        $canonicalHeaders = $this->canonicalHeaders($preparedHeaders);
        $payloadHash = 'UNSIGNED-PAYLOAD';

        $canonicalRequest = "{$method}\n{$this->uriPath($key)}\n{$canonicalQuery}\n{$canonicalHeaders}\n\n{$signedHeaders}\n{$payloadHash}";
        $stringToSign = "AWS4-HMAC-SHA256\n{$now}\n{$scope}\n" . hash('sha256', $canonicalRequest);
        $signature = $this->calculateSignature($stringToSign, $datestamp);

        return $uri . '?' . $canonicalQuery . '&X-Amz-Signature=' . $signature;
    }

    private function createPresignedUrlV2(string $method, string $key, int $expiresSeconds, array $headers = []): string
    {
        $baseUri = $this->buildRequestUri($key);
        $expires = time() + $expiresSeconds;

        $canonicalAmz = $this->canonicalizeAmzHeaders($headers);
        $canonicalResource = $this->canonicalResource($key, []);

        $stringToSign = sprintf(
            "%s\n\n\n%d\n%s%s",
            strtoupper($method),
            $expires,
            $canonicalAmz,
            $canonicalResource
        );

        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->secretKey, true));

        $query = [
            'AWSAccessKeyId' => $this->accessKey,
            'Expires' => (string) $expires,
            'Signature' => $signature,
        ];

        if ($this->sessionToken !== null) {
            $query['x-amz-security-token'] = $this->sessionToken;
        }

        return $baseUri . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function request(string $method, string $key, array $headers, ?string $body, array $query = []): array
    {
        [$status, $response, $rawHeaders] = $this->performRequest($method, $key, $headers, $body, $query, $this->signatureVersion);

        if (
            $this->autoDetectSignature
            && $this->signatureVersion === 'v4'
            && $status === 403
            && is_string($response)
            && str_contains($response, 'InvalidAccessKeyId')
        ) {
            [$fallbackStatus, $fallbackResponse, $fallbackHeaders] = $this->performRequest($method, $key, $headers, $body, $query, 'v2');

            if ($fallbackStatus < 400) {
                $this->signatureVersion = 'v2';
                $this->autoDetectSignature = false;
                return [$fallbackStatus, $fallbackResponse, $fallbackHeaders];
            }

            return [$fallbackStatus, $fallbackResponse, $fallbackHeaders];
        }

        if ($this->autoDetectSignature && $status < 400) {
            $this->autoDetectSignature = false;
        }

        return [$status, $response, $rawHeaders];
    }

    private function performRequest(string $method, string $key, array $headers, ?string $body, array $query, string $signatureVersion): array
    {
        $uri = $this->buildRequestUri($key, $query);
        $signedHeaders = $signatureVersion === 'v2'
            ? $this->signHeadersV2($method, $key, $headers, $query)
            : $this->signHeadersV4($method, $key, $headers, $body, $query);

        $headersList = $this->formatHeaders($signedHeaders);

        $context = stream_context_create([
            'http' => [
                'method'        => $method,
                'header'        => $headersList,
                'content'       => $body,
                'timeout'       => $this->timeout,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($uri, false, $context);
        $status = $this->extractStatus($http_response_header ?? []);

        if ($response === false) {
            $response = '';
        }

        return [$status, $response, $http_response_header ?? []];
    }

    private function signHeadersV4(string $method, string $key, array $headers, ?string $body = null, array $query = []): array
    {
        $now = gmdate('Ymd\THis\Z');
        $datestamp = substr($now, 0, 8);

        $headers['Host'] = $this->hostFor($key);
        $headers['X-Amz-Date'] = $now;

        if ($this->sessionToken !== null) {
            $headers['X-Amz-Security-Token'] = $this->sessionToken;
        }

        $headers['X-Amz-Content-Sha256'] = $body !== null ? hash('sha256', $body) : 'UNSIGNED-PAYLOAD';

        $canonicalHeaders = $this->canonicalHeaders($headers);
        $signedHeaders = $this->canonicalSignedHeaders($headers);
        $payloadHash = $body !== null ? hash('sha256', $body) : 'UNSIGNED-PAYLOAD';
        $canonicalQuery = $this->canonicalQueryString($query);

        $canonicalRequest = sprintf(
            "%s\n%s\n%s\n%s\n\n%s\n%s",
            $method,
            $this->uriPath($key),
            $canonicalQuery,
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash
        );

        $scope = sprintf('%s/%s/s3/aws4_request', $datestamp, $this->signingRegion);

        $stringToSign = sprintf(
            "AWS4-HMAC-SHA256\n%s\n%s\n%s",
            $now,
            $scope,
            hash('sha256', $canonicalRequest)
        );

        $signature = $this->calculateSignature($stringToSign, $datestamp);

        $headers['Authorization'] = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $this->accessKey,
            $scope,
            $signedHeaders,
            $signature
        );

        return $headers;
    }

    private function signHeadersV2(string $method, string $key, array $headers, array $query = []): array
    {
        $method = strtoupper($method);

        if (!isset($headers['Host']) && !isset($headers['host'])) {
            $headers['Host'] = $this->hostFor($key);
        }

        $date = gmdate('D, d M Y H:i:s \\G\\M\\T');

        if (!isset($headers['Date']) && !isset($headers['date']) && !isset($headers['x-amz-date']) && !isset($headers['X-Amz-Date'])) {
            $headers['Date'] = $date;
        }

        if ($this->sessionToken !== null && !isset($headers['x-amz-security-token']) && !isset($headers['X-Amz-Security-Token'])) {
            $headers['x-amz-security-token'] = $this->sessionToken;
        }

        $contentMd5 = $headers['Content-MD5'] ?? $headers['content-md5'] ?? '';
        $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? '';
        $dateHeader = $headers['Date'] ?? $headers['date'] ?? ($headers['X-Amz-Date'] ?? $headers['x-amz-date'] ?? $date);

        $canonicalAmz = $this->canonicalizeAmzHeaders($headers);
        $canonicalResource = $this->canonicalResource($key, $query);

        $stringToSign = sprintf(
            "%s\n%s\n%s\n%s\n%s%s",
            $method,
            $contentMd5,
            $contentType,
            $dateHeader,
            $canonicalAmz,
            $canonicalResource
        );

        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->secretKey, true));

        $headers['Authorization'] = sprintf('AWS %s:%s', $this->accessKey, $signature);

        return $headers;
    }

    private function calculateSignature(string $stringToSign, string $datestamp): string
    {
        $kDate = hash_hmac('sha256', $datestamp, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->signingRegion, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        return hash_hmac('sha256', $stringToSign, $kSigning);
    }

    private function applyBucketToHost(string $key): string
    {
        if ($this->endpoint === null) {
            if ($this->pathStyle) {
                return sprintf('s3.%s.amazonaws.com', $this->region);
            }

            return sprintf('%s.s3.%s.amazonaws.com', $this->bucket, $this->region);
        }

        $uri = parse_url($this->endpoint);
        $host = $uri['host'];

        if ($this->pathStyle) {
            return $host;
        }

        return $this->bucket . '.' . $host;
    }

    private function hostFor(string $key): string
    {
        return $this->applyBucketToHost($key);
    }

    private function buildRequestUri(string $key, array $query = []): string
    {
        $path = $this->uriPath($key);

        if ($this->endpoint === null) {
            return sprintf('https://%s%s', $this->applyBucketToHost($key), $path);
        }

        $uri = parse_url($this->endpoint);
        $host = $uri['host'];
        $scheme = $uri['scheme'] ?? 'https';
        $port = isset($uri['port']) ? ':' . $uri['port'] : '';

        if (! $this->pathStyle && $this->bucket !== '') {
            $host = $this->bucket . '.' . $host;
        }

        $queryString = $query === [] ? '' : '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return sprintf('%s://%s%s%s%s', $scheme, $host, $port, $path, $queryString);
    }

    private function uriPath(string $key): string
    {
        $path = $this->pathStyle ? '/' . $this->bucket . '/' . ltrim($key, '/') : '/' . ltrim($key, '/');

        return $this->rawurlencodePath($path);
    }

    private function canonicalHeaders(array $headers): string
    {
        ksort($headers, SORT_STRING | SORT_FLAG_CASE);

        $lines = [];

        foreach ($headers as $name => $value) {
            $lines[] = strtolower($name) . ':' . trim((string) $value);
        }

        return implode("\n", $lines) . "\n";
    }

    private function canonicalSignedHeaders(array $headers): string
    {
        $keys = array_map(static fn ($name): string => strtolower($name), array_keys($headers));
        sort($keys);

        return implode(';', $keys);
    }

    private function canonicalQueryString(array $params): string
    {
        ksort($params, SORT_STRING);
        $pairs = [];

        foreach ($params as $key => $value) {
            $pairs[] = rawurlencode($key) . '=' . rawurlencode($value);
        }

        return implode('&', $pairs);
    }

    private function canonicalizeAmzHeaders(array $headers): string
    {
        $amzHeaders = [];

        foreach ($headers as $name => $value) {
            $normalized = strtolower($name);

            if (!str_starts_with($normalized, 'x-amz-')) {
                continue;
            }

            if (is_array($value)) {
                $value = implode(',', $value);
            }

            $clean = preg_replace('/\s+/', ' ', trim((string) $value));
            $amzHeaders[$normalized][] = $clean;
        }

        if ($amzHeaders === []) {
            return '';
        }

        ksort($amzHeaders, SORT_STRING);

        $lines = [];

        foreach ($amzHeaders as $name => $values) {
            $lines[] = $name . ':' . implode(',', $values);
        }

        return implode("\n", $lines) . "\n";
    }

    private function canonicalResource(string $key, array $query = []): string
    {
        $resourcePath = '/' . ltrim($key, '/');

        if ($this->bucket !== '') {
            $resourcePath = '/' . $this->bucket . $resourcePath;
        }

        if ($query === []) {
            return $resourcePath;
        }

        $allowed = [
            'acl', 'lifecycle', 'location', 'logging', 'notification', 'partNumber',
            'policy', 'requestPayment', 'torrent', 'versionId', 'versioning', 'versions',
            'website', 'delete', 'uploads', 'uploadId', 'response-content-type',
            'response-content-language', 'response-expires', 'response-cache-control',
            'response-content-disposition', 'response-content-encoding', 'cors', 'restore',
            'tagging', 'replication', 'accelerate', 'inventory', 'metrics', 'analytics',
            'select', 'select-type',
        ];

        $subresources = [];

        foreach ($query as $name => $value) {
            if (!in_array($name, $allowed, true)) {
                continue;
            }

            if ($value === '' || $value === null) {
                $subresources[] = $name;
            } else {
                $subresources[] = $name . '=' . $value;
            }
        }

        if ($subresources === []) {
            return $resourcePath;
        }

        return $resourcePath . '?' . implode('&', $subresources);
    }

    private function listObjectsV2(string $prefix, ?string $continuationToken): array
    {
        $query = [
            'list-type' => '2',
            'max-keys' => '1000',
        ];

        if ($prefix !== '') {
            $query['prefix'] = $prefix;
        }

        if ($continuationToken !== null) {
            $query['continuation-token'] = $continuationToken;
        }

        return $this->request('GET', '', [], null, $query);
    }

    private function formatHeaders(array $headers): string
    {
        $lines = [];

        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return implode("\r\n", $lines);
    }

    private function extractStatus(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('#HTTP/\\d\\.\\d\\s+(\\d+)#', $header, $m)) {
                return (int) $m[1];
            }
        }

        return 0;
    }

    private function parseListV2Xml(string $xml): array
    {
        $sxe = @simplexml_load_string($xml);

        if ($sxe === false) {
            return ['RawXML' => $xml];
        }

        $out = [
            'Name'        => (string) $sxe->Name,
            'Prefix'      => (string) $sxe->Prefix,
            'KeyCount'    => (int) $sxe->KeyCount,
            'MaxKeys'     => (int) $sxe->MaxKeys,
            'IsTruncated' => ((string) $sxe->IsTruncated === 'true'),
        ];

        if (isset($sxe->NextContinuationToken)) {
            $out['NextContinuationToken'] = (string) $sxe->NextContinuationToken;
        }
        $out['Contents'] = [];

        foreach ($sxe->Contents ?? [] as $content) {
            $out['Contents'][] = [
                'Key'          => (string) $content->Key,
                'LastModified' => (string) $content->LastModified,
                'ETag'         => trim((string) $content->ETag, '"'),
                'Size'         => (int) $content->Size,
                'StorageClass' => (string) $content->StorageClass,
            ];
        }

        if (isset($sxe->CommonPrefixes)) {
            $out['CommonPrefixes'] = [];

            foreach ($sxe->CommonPrefixes as $prefix) {
                $out['CommonPrefixes'][] = (string) $prefix->Prefix;
            }
        }

        return $out;
    }

    private function rawurlencodePath(string $path): string
    {
        $segments = explode('/', $path);

        return implode('/', array_map('rawurlencode', $segments));
    }

    private function guessContentType(string $filepath): string
    {
        $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

        return match ($ext) {
            'txt' => 'text/plain',
            'html', 'htm' => 'text/html',
            'css' => 'text/css',
            'json' => 'application/json',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'svg' => 'image/svg+xml',
            'zip' => 'application/zip',
            default => 'application/octet-stream',
        };
    }

    private function contentMd5(string $data): string
    {
        return base64_encode(md5($data, true));
    }

    private function sha256Stream($stream): string
    {
        $ctx = hash_init('sha256');
        $pos = @ftell($stream);

        while (!feof($stream)) {
            $chunk = fread($stream, 1 << 20);

            if ($chunk === false) {
                break;
            }

            if ($chunk !== '') {
                hash_update($ctx, $chunk);
            }
        }

        if ($pos !== false) {
            @fseek($stream, $pos);
        }

        return hash_final($ctx);
    }

    private function rewindIfPossible($stream): void
    {
        if (@ftell($stream) !== false) {
            @rewind($stream);
        }
    }
}
