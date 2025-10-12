<?php

declare(strict_types=1);

namespace Zero\Lib\Storage\Drivers;

use DateTimeInterface;
use FilesystemIterator;
use InvalidArgumentException;
use RuntimeException;
use Zero\Lib\Filesystem\File;
use Zero\Lib\Http\Response;
use Zero\Lib\Http\UploadedFile;

final class LocalStorage
{
    public function __construct(private array $config, private string $diskName = 'local')
    {
    }

    /**
     * Persist raw contents or a File object to disk, copying metadata when available.
     */
    public function put(string $path, string|File $contents): string
    {
        $fullPath = $this->fullPath($path);
        $this->ensureDirectory(dirname($fullPath));

        if ($contents instanceof File) {
            $contents->copyTo($fullPath, true);

            return $this->relativePath($fullPath);
        }

        if (file_put_contents($fullPath, $contents) === false) {
            throw new RuntimeException(sprintf('Unable to write file at [%s].', $fullPath));
        }

        return $this->relativePath($fullPath);
    }

    public function putFile(string $directory, File $file): string
    {
        return $this->putFileAs($directory, $file, $this->uniqueFileName($file));
    }

    public function putFileAs(string $directory, File $file, string $name): string
    {
        $target = $this->fullPath(trim($directory, '/') . '/' . ltrim($name, '/'));
        $this->ensureDirectory(dirname($target));

        if ($file instanceof UploadedFile) {
            $file->move($target);
        } else {
            $file->copyTo($target);
        }

        return $this->relativePath($target);
    }

    public function get(string|File $path): string
    {
        if ($path instanceof File) {
            $path = $this->relativePath($path->getPath());
        }

        $fullPath = $this->fullPath($path);

        if (!is_file($fullPath)) {
            throw new RuntimeException(sprintf('File [%s] does not exist.', $fullPath));
        }

        $contents = file_get_contents($fullPath);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read file at [%s].', $fullPath));
        }

        return $contents;
    }

    public function exists(string $path): bool
    {
        return is_file($this->fullPath($path));
    }

    /**
     * @return File[]
     */
    public function files(string $directory = '', bool $recursive = false): array
    {
        $root = $this->fullPath($directory);

        if (!is_dir($root)) {
            return [];
        }

        $paths = $recursive
            ? $this->collectRecursiveFilePaths($root, trim($directory, '/'))
            : $this->listShallowFilePaths($root);

        return array_map(function (string $relative): File {
            $absolute = $this->fullPath($relative);

            $metadata = [
                '_storage_path' => ltrim($relative, '/'),
                '_storage_disk' => $this->diskName,
            ];

            return File::fromPath($absolute, true, $metadata)->withDisk($this->diskName);
        }, $paths);
    }

    public function url(string $path): string
    {
        $normalized = ltrim($path, '/');
        $base = isset($this->config['url']) ? rtrim((string) $this->config['url'], '/') : '';

        if ($base !== '') {
            return $base . '/' . $normalized;
        }

        return $this->fullPath($normalized);
    }

    public function temporaryUrl(string $path, DateTimeInterface|int $expiration): string
    {
        $timestamp = $this->normalizeExpiration($expiration);

        if ($timestamp <= time()) {
            throw new InvalidArgumentException('Temporary URLs must expire in the future.');
        }

        $url = $this->url($path);
        $signature = $this->signTemporaryUrl($path, $timestamp);

        $query = http_build_query([
            'path' => ltrim($path, '/'),
            'expires' => $timestamp,
            'signature' => $signature,
        ], '', '&', PHP_QUERY_RFC3986);

        return $url . (str_contains($url, '?') ? '&' : '?') . $query;
    }

    public function response(string $path, array $options = []): Response
    {
        $absolute = $this->fullPath($path);

        if (!is_file($absolute)) {
            throw new RuntimeException(sprintf('File [%s] does not exist.', $absolute));
        }

        $headers = $options['headers'] ?? [];
        if (!isset($headers['Cache-Control'])) {
            $headers['Cache-Control'] = 'private, max-age=0, must-revalidate';
        }

        $name = $options['name'] ?? basename($path);
        $disposition = $options['disposition'] ?? 'inline';

        return Response::file($absolute, $headers, $name, $disposition);
    }

    private function normalizeExpiration(DateTimeInterface|int $expiration): int
    {
        if ($expiration instanceof DateTimeInterface) {
            return $expiration->getTimestamp();
        }

        return (int) $expiration;
    }

    private function signTemporaryUrl(string $path, int $expires): string
    {
        $key = $this->temporaryUrlKey();
        $payload = ltrim($path, '/') . '|' . $expires;

        return hash_hmac('sha256', $payload, $key);
    }

    private function temporaryUrlKey(): string
    {
        $key = env('APP_KEY');

        if (!is_string($key) || trim($key) === '') {
            throw new RuntimeException('Temporary URL generation requires a valid APP_KEY.');
        }

        return $key;
    }

    private function uniqueFileName(File $file): string
    {
        if ($file instanceof UploadedFile) {
            $name = $file->getClientFilename();
            $extension = $file->getClientExtension();
        } else {
            $name = $file->getFilename();
            $extension = $file->getExtension();
        }

        $slug = preg_replace('/[^A-Za-z0-9_\-]+/', '-', $name) ?: 'file';
        $extension = $extension !== '' ? '.' . $extension : '';

        return $slug . '-' . uniqid() . $extension;
    }

    private function fullPath(string $path): string
    {
        $root = rtrim($this->config['root'] ?? storage_path(), '/');

        return $root . '/' . ltrim($path, '/');
    }

    private function relativePath(string $fullPath): string
    {
        $root = rtrim($this->config['root'] ?? storage_path(), '/');

        return ltrim(str_replace($root, '', $fullPath), '/');
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Unable to create directory [%s].', $path));
        }
    }

    /**
     * @return string[]
     */
    private function listShallowFilePaths(string $root): array
    {
        $files = [];

        $iterator = new FilesystemIterator($root, FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $item) {
            if (! $item->isFile()) {
                continue;
            }

            $files[] = $this->relativePath($item->getPathname());
        }

        sort($files);

        return $files;
    }

    /**
     * @return string[]
     */
    private function collectRecursiveFilePaths(string $root, string $prefix): array
    {
        $files = [];

        $iterator = new FilesystemIterator($root, FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $item) {
            $relativePath = ltrim(($prefix === '' ? '' : $prefix . '/') . $item->getBasename(), '/');

            if ($item->isDir()) {
                $files = array_merge($files, $this->collectRecursiveFilePaths($item->getPathname(), $relativePath));
                continue;
            }

            if ($item->isFile()) {
                $files[] = $relativePath;
            }
        }

        sort($files);

        return $files;
    }
}
