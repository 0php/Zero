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

    /**
     * Delete one or more files. Returns true when every target was removed
     * (missing files count as success — desired state is "gone").
     *
     * @param string|array<int, string> $paths
     */
    public function delete(string|array $paths): bool
    {
        $paths = is_array($paths) ? $paths : [$paths];
        $ok = true;

        foreach ($paths as $path) {
            $full = $this->fullPath($path);

            if (! file_exists($full)) {
                continue;
            }

            if (! is_file($full) || ! @unlink($full)) {
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * Recursively remove a directory and everything under it.
     */
    public function deleteDirectory(string $directory): bool
    {
        $full = $this->fullPath($directory);

        if (! is_dir($full)) {
            return true;
        }

        return $this->removeTree($full);
    }

    /**
     * Copy a file within the disk. Returns false when the source is missing.
     */
    public function copy(string $from, string $to): bool
    {
        $source = $this->fullPath($from);
        $target = $this->fullPath($to);

        if (! is_file($source)) {
            return false;
        }

        $this->ensureDirectory(dirname($target));

        return @copy($source, $target);
    }

    /**
     * Move (rename) a file within the disk.
     */
    public function move(string $from, string $to): bool
    {
        $source = $this->fullPath($from);
        $target = $this->fullPath($to);

        if (! is_file($source)) {
            return false;
        }

        $this->ensureDirectory(dirname($target));

        return @rename($source, $target);
    }

    /**
     * Prepend $data to the file. Creates the file when it does not exist.
     */
    public function prepend(string $path, string $data): string
    {
        $existing = $this->exists($path) ? $this->get($path) : '';

        return $this->put($path, $data . $existing);
    }

    /**
     * Append $data to the file. Creates the file when it does not exist.
     */
    public function append(string $path, string $data): string
    {
        $full = $this->fullPath($path);
        $this->ensureDirectory(dirname($full));

        if (file_put_contents($full, $data, FILE_APPEND) === false) {
            throw new RuntimeException(sprintf('Unable to append to file at [%s].', $full));
        }

        return $this->relativePath($full);
    }

    /**
     * List immediate or recursive sub-directories.
     *
     * @return array<int, string>
     */
    public function directories(string $directory = '', bool $recursive = false): array
    {
        $root = $this->fullPath($directory);

        if (! is_dir($root)) {
            return [];
        }

        $results = $recursive
            ? $this->collectRecursiveDirectoryPaths($root, trim($directory, '/'))
            : $this->listShallowDirectoryPaths($root);

        sort($results);

        return $results;
    }

    /**
     * Create a directory (recursively if needed).
     */
    public function makeDirectory(string $path): bool
    {
        $full = $this->fullPath($path);

        if (is_dir($full)) {
            return true;
        }

        return @mkdir($full, 0775, true);
    }

    public function size(string $path): int
    {
        $full = $this->fullPath($path);

        if (! is_file($full)) {
            throw new RuntimeException(sprintf('File [%s] does not exist.', $full));
        }

        $size = filesize($full);

        return $size === false ? 0 : (int) $size;
    }

    public function lastModified(string $path): int
    {
        $full = $this->fullPath($path);

        if (! is_file($full)) {
            throw new RuntimeException(sprintf('File [%s] does not exist.', $full));
        }

        $mtime = filemtime($full);

        return $mtime === false ? 0 : (int) $mtime;
    }

    public function mimeType(string $path): string
    {
        $full = $this->fullPath($path);

        if (! is_file($full)) {
            throw new RuntimeException(sprintf('File [%s] does not exist.', $full));
        }

        if (function_exists('mime_content_type')) {
            $type = @mime_content_type($full);

            if (is_string($type) && $type !== '') {
                return $type;
            }
        }

        return 'application/octet-stream';
    }

    /**
     * Open the file for reading. Caller must fclose() the returned resource.
     *
     * @return resource
     */
    public function readStream(string $path)
    {
        $full = $this->fullPath($path);

        if (! is_file($full)) {
            throw new RuntimeException(sprintf('File [%s] does not exist.', $full));
        }

        $handle = @fopen($full, 'rb');

        if ($handle === false) {
            throw new RuntimeException(sprintf('Unable to open file at [%s] for reading.', $full));
        }

        return $handle;
    }

    /**
     * Pipe a readable stream into the disk. Does not close the supplied stream.
     *
     * @param resource $stream
     */
    public function writeStream(string $path, $stream): string
    {
        if (! is_resource($stream)) {
            throw new InvalidArgumentException('writeStream() expects a stream resource.');
        }

        $full = $this->fullPath($path);
        $this->ensureDirectory(dirname($full));

        $target = @fopen($full, 'wb');

        if ($target === false) {
            throw new RuntimeException(sprintf('Unable to open file at [%s] for writing.', $full));
        }

        if (stream_copy_to_stream($stream, $target) === false) {
            fclose($target);
            throw new RuntimeException(sprintf('Unable to write stream to [%s].', $full));
        }

        fclose($target);

        return $this->relativePath($full);
    }

    /**
     * Apply POSIX permissions appropriate for "public" or "private" visibility.
     */
    public function setVisibility(string $path, string $visibility): bool
    {
        $full = $this->fullPath($path);

        if (! file_exists($full)) {
            return false;
        }

        $mode = $this->visibilityMode($visibility, is_dir($full));

        return @chmod($full, $mode);
    }

    /**
     * Inverse of setVisibility(). Returns 'public' or 'private'.
     */
    public function getVisibility(string $path): string
    {
        $full = $this->fullPath($path);

        if (! file_exists($full)) {
            throw new RuntimeException(sprintf('Path [%s] does not exist.', $full));
        }

        $perms = @fileperms($full);

        if ($perms === false) {
            return 'private';
        }

        // Other-readable bit (0004) controls "world-can-read", which we treat
        // as "public" in the storage abstraction.
        return ($perms & 0004) === 0004 ? 'public' : 'private';
    }

    private function visibilityMode(string $visibility, bool $isDir): int
    {
        return match ($visibility) {
            'public' => $isDir ? 0775 : 0664,
            'private' => $isDir ? 0700 : 0600,
            default => throw new InvalidArgumentException(sprintf('Unsupported visibility [%s]. Use public or private.', $visibility)),
        };
    }

    private function removeTree(string $directory): bool
    {
        $iterator = new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS);
        $ok = true;

        foreach ($iterator as $item) {
            if ($item->isDir() && ! $item->isLink()) {
                $ok = $this->removeTree($item->getPathname()) && $ok;
            } else {
                $ok = @unlink($item->getPathname()) && $ok;
            }
        }

        return @rmdir($directory) && $ok;
    }

    /**
     * @return array<int, string>
     */
    private function listShallowDirectoryPaths(string $root): array
    {
        $dirs = [];
        $iterator = new FilesystemIterator($root, FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $item) {
            if (! $item->isDir()) {
                continue;
            }

            $dirs[] = $this->relativePath($item->getPathname());
        }

        return $dirs;
    }

    /**
     * @return array<int, string>
     */
    private function collectRecursiveDirectoryPaths(string $root, string $prefix): array
    {
        $dirs = [];
        $iterator = new FilesystemIterator($root, FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $item) {
            if (! $item->isDir()) {
                continue;
            }

            $relativePath = ltrim(($prefix === '' ? '' : $prefix . '/') . $item->getBasename(), '/');
            $dirs[] = $relativePath;
            $dirs = array_merge($dirs, $this->collectRecursiveDirectoryPaths($item->getPathname(), $relativePath));
        }

        return $dirs;
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
