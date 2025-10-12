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
