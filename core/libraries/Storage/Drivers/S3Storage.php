<?php

declare(strict_types=1);

namespace Zero\Lib\Storage\Drivers;

use RuntimeException;
use Zero\Lib\Filesystem\File;
use Zero\Lib\Filesystem\RemoteFile;
use Zero\Lib\Http\Response;
use Zero\Lib\Storage\Adapters\S3Adapter;


/**
 * Storage driver backed by an S3-compatible bucket using the internal adapter.
 */
final class S3Storage
{
    private S3Adapter $client;
    private string $root;
    private string $diskName;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config, string $diskName = 's3')
    {
        $this->diskName = $diskName;
        $this->client = new S3Adapter(
            (string)($config['key'] ?? ''),
            (string)($config['secret'] ?? ''),
            (string)($config['region'] ?? 'us-east-1'),
            (string)($config['bucket'] ?? ''),
            null,
            (int)($config['timeout'] ?? 60),
            isset($config['signing_region']) && $config['signing_region'] !== null
                ? (string) $config['signing_region']
                : null,
            isset($config['signature_version']) && $config['signature_version'] !== null
                ? (string) $config['signature_version']
                : null
        );

        if (! empty($config['endpoint'])) {
            $this->client->setEndpoint((string)$config['endpoint'], (bool)($config['path_style'] ?? true));
        }

        if (! empty($config['acl'])) {
            $this->client->setDefaultAcl((string)$config['acl']);
        }

        $this->root = trim((string)($config['root'] ?? ($config['root_path'] ?? '')), '/');
    }

    /**
     * Persist raw contents or an existing File to the S3 disk.
     */
    public function put(string $path, string|File $contents): string
    {
        $key = $this->applyRoot($path);

        if ($contents instanceof File) {
            $headers = $this->buildUploadHeaders($key, $contents);
            $this->client->putObjectFromFile($key, $contents->getPath(), $headers);

            return $path;
        }

        $this->client->putObject($key, $contents);

        return $path;
    }

    /** Store a file inside the given directory using its basename. */
    public function putFile(string $directory, File $file): string
    {
        $key = trim($directory, '/') . '/' . $file->getBasename();

        return $this->put($key, $file);
    }

    /** Store a file inside the given directory using a custom name. */
    public function putFileAs(string $directory, File $file, string $name): string
    {
        $key = trim($directory, '/') . '/' . ltrim($name, '/');

        return $this->put($key, $file);
    }

    /** Retrieve the contents of an object (path or File instance). */
    public function get(string|File $path): string
    {
        $key = $path instanceof File ? $this->applyRoot($path->getPath()) : $this->applyRoot($path);

        return $this->client->getObject($key)[1];
    }

    /** Check object existence via HEAD request. */
    public function exists(string $path): bool
    {
        return $this->client->headObject($this->applyRoot($path))[0] === 200;
    }

    /**
     * @return File[]
     */
    public function files(string $directory = '', bool $recursive = false): array
    {
        $prefix = trim($directory, '/') . '/';
        $prefix = ltrim($this->applyRoot($prefix), '/');
        $objects = [];

        foreach ($this->client->listAllObjects($prefix) as $object) {
            $key = $object['Key'];

            if (str_ends_with($key, '.meta.json')) {
                // Ignore legacy sidecar metadata files.
                continue;
            }

            if (str_ends_with($key, '/')) {
                // Skip directory placeholders (zero-byte keys ending with slash).
                continue;
            }

            if (! $recursive) {
                $remainder = substr($key, strlen($prefix));
                if (str_contains($remainder, '/')) {
                    continue;
                }
            }
            $relativePath = $this->stripRootFromKey($key);

            if ($relativePath === '') {
                continue;
            }

            $metadata = $this->fetchObjectMetadata($key);

            if (!isset($metadata['_size'])) {
                $metadata['_size'] = (int) ($object['Size'] ?? 0);
            }

            if (!isset($metadata['_content_length'])) {
                $metadata['_content_length'] = (int) ($object['Size'] ?? 0);
            }

            if (!isset($metadata['_etag']) && isset($object['ETag'])) {
                $metadata['_etag'] = trim((string) $object['ETag'], '"');
            }

            if (!isset($metadata['last_modified']) && isset($object['LastModified'])) {
                $metadata['last_modified'] = $object['LastModified'];
            }

            if (!isset($metadata['_remote_path'])) {
                $metadata['_remote_path'] = $relativePath;
            }

            if (!isset($metadata['_remote_key'])) {
                $metadata['_remote_key'] = ltrim($key, '/');
            }

            if (!isset($metadata['_storage_path'])) {
                $metadata['_storage_path'] = $relativePath;
            }

            $metadata['_storage_disk'] = $this->diskName;

            $objects[] = RemoteFile::fromMetadata($relativePath, $metadata, $this->diskName);
        }

        return $objects;
    }

    /** Generate a short-lived GET URL (300 seconds). */
    public function url(string $path): string
    {
        return $this->client->createPresignedUrl('GET', $this->applyRoot($path), 300);
    }

    /** Generate a presigned GET URL honoring the supplied expiration. */
    public function temporaryUrl(string $path, \DateTimeInterface|int $expiration): string
    {
        $seconds = $expiration instanceof \DateTimeInterface ? $expiration->getTimestamp() - time() : (int)$expiration;

        return $this->client->createPresignedUrl('GET', $this->applyRoot($path), $seconds);
    }

    /**
     * Delete one or more objects.
     *
     * @param string|array<int, string> $paths
     */
    public function delete(string|array $paths): bool
    {
        $paths = is_array($paths) ? $paths : [$paths];
        $ok = true;

        foreach ($paths as $path) {
            $status = $this->client->deleteObject($this->applyRoot($path));

            if ($status >= 300) {
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * Delete every object under a "directory" prefix.
     */
    public function deleteDirectory(string $directory): bool
    {
        $prefix = trim($directory, '/');
        if ($prefix === '') {
            return false; // refuse to wipe the entire bucket — use with intent
        }

        $prefixedKey = ltrim($this->applyRoot($prefix . '/'), '/');
        $ok = true;

        foreach ($this->client->listAllObjects($prefixedKey) as $object) {
            $status = $this->client->deleteObject($object['Key']);
            if ($status >= 300) {
                $ok = false;
            }
        }

        return $ok;
    }

    public function copy(string $from, string $to): bool
    {
        $status = $this->client->copyObject($this->applyRoot($from), $this->applyRoot($to));

        return $status >= 200 && $status < 300;
    }

    public function move(string $from, string $to): bool
    {
        if (! $this->copy($from, $to)) {
            return false;
        }

        return $this->delete($from);
    }

    /**
     * Read-modify-write the object with $data prepended. Cheap object,
     * expensive on big ones — at-least-once semantics with no atomicity.
     */
    public function prepend(string $path, string $data): string
    {
        $existing = $this->exists($path) ? $this->get($path) : '';

        return $this->put($path, $data . $existing);
    }

    public function append(string $path, string $data): string
    {
        $existing = $this->exists($path) ? $this->get($path) : '';

        return $this->put($path, $existing . $data);
    }

    /**
     * List "directory" prefixes inside $directory. S3 has no real directories,
     * so this returns common prefixes derived from object keys.
     *
     * @return array<int, string>
     */
    public function directories(string $directory = '', bool $recursive = false): array
    {
        $prefix = trim($directory, '/');
        $rootPrefix = ltrim($this->applyRoot($prefix === '' ? '' : $prefix . '/'), '/');

        $results = [];

        foreach ($this->client->listAllObjects($rootPrefix) as $object) {
            $key = $object['Key'];

            if (str_ends_with($key, '.meta.json')) {
                continue;
            }

            $remainder = $rootPrefix === '' ? $key : substr($key, strlen($rootPrefix));

            if ($remainder === false || $remainder === '') {
                continue;
            }

            // Walk every "/" separator in the remainder so we can collect
            // both shallow and (when $recursive) nested prefixes.
            $segments = explode('/', $remainder);
            array_pop($segments); // drop the file basename

            if ($segments === []) {
                continue;
            }

            $accumulated = $prefix === '' ? '' : $prefix;

            foreach ($segments as $i => $segment) {
                if ($segment === '') {
                    continue;
                }

                $accumulated = $accumulated === '' ? $segment : $accumulated . '/' . $segment;

                if (! $recursive && $i > 0) {
                    break;
                }

                $results[$accumulated] = true;
            }
        }

        ksort($results);

        return array_keys($results);
    }

    /**
     * S3 has no native directory primitive — emulated by writing a 0-byte
     * key ending in "/", which keeps the prefix visible to console UIs.
     */
    public function makeDirectory(string $path): bool
    {
        $key = rtrim($this->applyRoot($path), '/') . '/';

        return $this->client->putObject($key, '') >= 200 && true;
    }

    public function size(string $path): int
    {
        [$status, , $headers] = $this->client->headObject($this->applyRoot($path));

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf('Object [%s] does not exist.', $path));
        }

        return (int) $this->headerValue($headers, 'Content-Length');
    }

    public function lastModified(string $path): int
    {
        [$status, , $headers] = $this->client->headObject($this->applyRoot($path));

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf('Object [%s] does not exist.', $path));
        }

        $value = $this->headerValue($headers, 'Last-Modified');

        if ($value === '') {
            return 0;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? 0 : $timestamp;
    }

    public function mimeType(string $path): string
    {
        [$status, , $headers] = $this->client->headObject($this->applyRoot($path));

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf('Object [%s] does not exist.', $path));
        }

        $value = $this->headerValue($headers, 'Content-Type');

        return $value !== '' ? $value : 'application/octet-stream';
    }

    /**
     * Stream an object's body. Caller must fclose() the returned resource.
     *
     * @return resource
     */
    public function readStream(string $path)
    {
        $stream = $this->client->streamObject($this->applyRoot($path));

        if ($stream === false) {
            throw new RuntimeException(sprintf('Unable to read object [%s].', $path));
        }

        return $stream;
    }

    /**
     * Upload from an open stream. Buffers into memory because the underlying
     * adapter expects a complete body — fine for small/medium files; use
     * putFile() with a pre-saved File for large uploads to avoid memory
     * pressure.
     *
     * @param resource $stream
     */
    public function writeStream(string $path, $stream): string
    {
        if (! is_resource($stream)) {
            throw new \InvalidArgumentException('writeStream() expects a stream resource.');
        }

        $contents = stream_get_contents($stream);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read source stream for [%s].', $path));
        }

        return $this->put($path, $contents);
    }

    /**
     * Apply a public-read or private ACL to the object.
     */
    public function setVisibility(string $path, string $visibility): bool
    {
        $acl = match ($visibility) {
            'public' => 'public-read',
            'private' => 'private',
            default => throw new \InvalidArgumentException(sprintf('Unsupported visibility [%s]. Use public or private.', $visibility)),
        };

        $key = $this->applyRoot($path);

        if (! $this->exists($path)) {
            return false;
        }

        // Re-issue a copy onto the same key with the new ACL header.
        $status = $this->client->copyObject($key, $key, [
            'x-amz-acl' => $acl,
            'x-amz-metadata-directive' => 'COPY',
        ]);

        return $status >= 200 && $status < 300;
    }

    /**
     * Best-effort visibility readout: unknown returns 'private' (the safe
     * default). Many S3-compatible providers do not surface ACL via HEAD;
     * relying on returned values for security checks is not recommended.
     */
    public function getVisibility(string $path): string
    {
        return $this->exists($path) ? 'private' : throw new RuntimeException(sprintf('Object [%s] does not exist.', $path));
    }

    /**
     * @param array<int, string> $headers
     */
    private function headerValue(array $headers, string $name): string
    {
        $needle = strtolower($name);

        foreach ($headers as $line) {
            if (! is_string($line) || ! str_contains($line, ':')) {
                continue;
            }

            [$header, $value] = explode(':', $line, 2);

            if (strtolower(trim($header)) === $needle) {
                return trim($value);
            }
        }

        return '';
    }

    /** Stream an object directly to the HTTP response. */
    public function response(string $path, array $options = []): Response
    {
        $key = $this->applyRoot($path);
        $stream = $this->client->streamObject($key);

        if ($stream === false) {
            throw new RuntimeException(sprintf('Unable to stream object [%s].', $path));
        }

        return Response::stream(static function () use ($stream): void {
            while (!feof($stream)) {
                $chunk = fread($stream, 1048576);
                if ($chunk === false) {
                    break;
                }
                echo $chunk;
                flush();
            }

            fclose($stream);
        }, 200, [
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ], 'application/octet-stream');
    }

    private function applyRoot(string $path): string
    {
        $path = ltrim($path, '/');

        if ($this->root === '') {
            return $path;
        }

        return $this->root . '/' . $path;
    }

    /**
     * Build upload headers for a File, embedding metadata inside the S3 object.
     *
     * @return array<string, string>
     */
    private function buildUploadHeaders(string $key, File $file): array
    {
        $mime = trim((string) $file->getMimeType());
        $headers = [
            'Content-Type' => $mime !== '' ? $mime : 'application/octet-stream',
        ];

        $metadata = $file->metadata();
        $metadata['_remote_path'] ??= $this->stripRootFromKey($key);
        $metadata['_remote_key'] ??= ltrim($key, '/');

        $encoded = $this->encodeMetadata($metadata);

        if ($encoded !== null) {
            $headers['x-amz-meta-zero-metadata'] = $encoded;
        }

        return $headers;
    }

    /**
     * Retrieve metadata from the remote object (legacy sidecars supported).
     *
     * @return array<string, mixed>
     */
    private function fetchObjectMetadata(string $key): array
    {
        try {
            [$status, , $headers] = $this->client->headObject($key);

            if ($status >= 200 && $status < 300) {
                $metadata = $this->decodeMetadataFromHeaders($headers);

                if ($metadata !== []) {
                    return $metadata;
                }
            }
        } catch (\Throwable) {
            // Ignore and fall back to legacy metadata lookup.
        }

        return $this->fetchLegacyMetadata($key);
    }

    /**
     * Attempt to fetch legacy sidecar metadata objects for backward compatibility.
     *
     * @return array<string, mixed>
     */
    private function fetchLegacyMetadata(string $key): array
    {
        try {
            [, $metaBody] = $this->client->getObject($this->metadataKey($key));
            $decoded = json_decode($metaBody, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        } catch (\Throwable) {
            // No legacy metadata available.
        }

        return [];
    }

    private function metadataKey(string $key): string
    {
        return $key . '.meta.json';
    }

    /**
     * Decode the embedded metadata header from a HEAD/GET response.
     *
     * @param array<int, string> $headers
     *
     * @return array<string, mixed>
     */
    private function decodeMetadataFromHeaders(array $headers): array
    {
        $needle = 'x-amz-meta-zero-metadata';

        foreach ($headers as $header) {
            if (!is_string($header) || !str_contains($header, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $header, 2);
            $normalized = strtolower(trim($name));

            if ($normalized !== $needle) {
                continue;
            }

            $decoded = base64_decode(trim($value), true);

            if ($decoded === false) {
                return [];
            }

            $metadata = json_decode($decoded, true);

            return is_array($metadata) ? $metadata : [];
        }

        return [];
    }

    /**
     * Encode metadata for storage inside S3 object headers.
     *
     * @param array<string, mixed> $metadata
     */
    private function encodeMetadata(array $metadata): ?string
    {
        if ($metadata === []) {
            return null;
        }

        $encoded = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            return null;
        }

        return base64_encode($encoded);
    }

    private function stripRootFromKey(string $key): string
    {
        $normalizedKey = ltrim($key, '/');
        $root = trim($this->root, '/');

        if ($root === '') {
            return $normalizedKey;
        }

        if ($normalizedKey === $root) {
            return '';
        }

        if (str_starts_with($normalizedKey, $root . '/')) {
            return substr($normalizedKey, strlen($root) + 1);
        }

        return $normalizedKey;
    }
}
