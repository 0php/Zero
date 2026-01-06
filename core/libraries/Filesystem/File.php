<?php

declare(strict_types=1);

namespace Zero\Lib\Filesystem;

use RuntimeException;
use SplFileInfo;
use Zero\Lib\Storage\Storage;
use function config;

/**
 * Lightweight filesystem helper that wraps common file operations.
 *
 * The class intentionally avoids external dependencies so it can be reused by
 * HTTP uploads, storage drivers, and application code that needs direct file
 * management helpers (copy/move/delete, metadata inspection, etc.).
 */

class File
{
    protected string $path;
    protected array $metadata = [];
    protected ?string $disk = null;

    public function __construct(string $path, bool $ensureExists = true, array $metadata = [])
    {
        $this->path = $path;
        $this->metadata = $metadata;

        if ($ensureExists && !is_file($path)) {
            throw new RuntimeException(sprintf('File [%s] does not exist.', $path));
        }
    }

    public function withDisk(?string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function disk(): ?string
    {
        return $this->disk;
    }

    public static function fromPath(string $path, bool $ensureExists = true, array $metadata = []): static
    {
        return new static($path, $ensureExists, $metadata);
    }

    /**
     * Create a temporary file instance from raw contents.
     *
     * Useful when you need to push arbitrary data into the storage layer
     * without first writing it to disk yourself.
     */
    public static function fromContents(string $contents, ?string $extension = null, array $metadata = []): static
    {
        $tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        $filename = uniqid('zero_file_', true);
        if ($extension !== null && $extension !== '') {
            $filename .= '.' . ltrim($extension, '.');
        }

        $tempPath = $tempDir . DIRECTORY_SEPARATOR . $filename;

        static::ensureDirectory(dirname($tempPath));

        if (file_put_contents($tempPath, $contents) === false) {
            throw new RuntimeException(sprintf('Unable to write temporary file [%s].', $tempPath));
        }

        return new static($tempPath, false, $metadata);
    }

    /**
     * Create a file instance from a base64-encoded payload (data URI friendly).
     */
    public static function fromBase64(string $payload, ?string $extension = null, array $metadata = []): static
    {
        if (str_contains($payload, ',')) {
            [$meta, $payload] = explode(',', $payload, 2);
            if ($extension === null && str_starts_with($meta, 'data:') && str_contains($meta, ';base64')) {
                $mime = substr($meta, 5, strpos($meta, ';') - 5);
                $extension = explode('/', $mime)[1] ?? $extension;
            }
        }

        $decoded = base64_decode($payload, true);

        if ($decoded === false) {
            throw new RuntimeException('Invalid base64 payload provided.');
        }

        return static::fromContents($decoded, $extension, $metadata);
    }

    /**
     * Download a remote file and wrap it as a File instance.
     *
     * @param array|resource|null $context Optional stream context options
     */
    public static function fromUrl(string $url, ?string $extension = null, array $metadata = [], mixed $context = null): static
    {
        $contextResource = null;

        if ($context !== null) {
            $contextResource = stream_context_create($context);
        }

        $contents = @file_get_contents($url, false, $contextResource);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to download file from URL [%s].', $url));
        }

        if ($extension === null) {
            $pathInfo = pathinfo(parse_url($url, PHP_URL_PATH) ?? '');
            $extension = $pathInfo['extension'] ?? null;
        }

        return static::fromContents($contents, $extension, $metadata);
    }

    /**
     * Smart factory that accepts a path, URL, base64 payload, or raw contents.
     *
     * The helper inspects the source and delegates to the appropriate factory.
     */
    public static function from(string $source, ?string $extension = null, array $metadata = []): static
    {
        if (is_file($source)) {
            return static::fromPath($source, true, $metadata);
        }

        if (filter_var($source, FILTER_VALIDATE_URL)) {
            return static::fromUrl($source, $extension, $metadata);
        }

        $decoded = base64_decode($source, true);
        if ($decoded !== false && base64_encode($decoded) === str_replace(["\r", "\n"], '', $source)) {
            return static::fromBase64($source, $extension, $metadata);
        }

        return static::fromContents($source, $extension, $metadata);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function getDirectory(): string
    {
        return dirname($this->path);
    }

    public function getBasename(): string
    {
        return basename($this->path);
    }

    public function getFilename(): string
    {
        return pathinfo($this->path, PATHINFO_FILENAME);
    }

    public function getExtension(): string
    {
        return pathinfo($this->path, PATHINFO_EXTENSION);
    }

    public function getSize(): int
    {
        if (! $this->exists()) {
            return 0;
        }

        $size = filesize($this->path);

        if ($size === false) {
            throw new RuntimeException(sprintf('Unable to determine size for [%s].', $this->path));
        }

        return (int) $size;
    }

    public function getMimeType(): string
    {
        if (! $this->exists()) {
            throw new RuntimeException(sprintf('File [%s] does not exist.', $this->path));
        }

        $metadata = $this->metadata();

        if (isset($metadata['_override_mime']) && is_string($metadata['_override_mime'])) {
            return $metadata['_override_mime'];
        }

        return mime_content_type($this->path) ?: 'application/octet-stream';
    }

    public function lastModified(): int
    {
        $modified = filemtime($this->path);

        if ($modified === false) {
            throw new RuntimeException(sprintf('Unable to read last modification time for [%s].', $this->path));
        }

        return $modified;
    }

    public function read(): string
    {
        if (! $this->exists()) {
            throw new RuntimeException(sprintf('File [%s] does not exist.', $this->path));
        }

        $contents = file_get_contents($this->path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read file [%s].', $this->path));
        }

        return $contents;
    }

    public function write(string $contents, bool $append = false): static
    {
        static::ensureDirectory(dirname($this->path));

        $flags = $append ? FILE_APPEND : 0;

        if (file_put_contents($this->path, $contents, $flags) === false) {
            throw new RuntimeException(sprintf('Unable to write file [%s].', $this->path));
        }

        return $this;
    }

    public function append(string $contents): static
    {
        return $this->write($contents, true);
    }

    public function delete(): void
    {
        if (! $this->exists()) {
            return;
        }

        if (!@unlink($this->path)) {
            throw new RuntimeException(sprintf('Unable to delete file [%s].', $this->path));
        }
    }

    public function copyTo(string $targetPath, bool $overwrite = false): File
    {
        static::ensureDirectory(dirname($targetPath));

        $targetExists = is_file($targetPath);

        if ($targetExists && !$overwrite) {
            throw new RuntimeException(sprintf('File [%s] already exists.', $targetPath));
        }

        if ($targetExists && $overwrite && !@unlink($targetPath)) {
            throw new RuntimeException(sprintf('Unable to overwrite file at [%s].', $targetPath));
        }

        if (!@copy($this->path, $targetPath)) {
            throw new RuntimeException(sprintf('Unable to copy [%s] to [%s].', $this->path, $targetPath));
        }

        return new self($targetPath, true, $this->metadata);
    }

    public function moveTo(string $targetPath, bool $overwrite = false): static
    {
        static::ensureDirectory(dirname($targetPath));

        $targetExists = is_file($targetPath);

        if ($targetExists && !$overwrite) {
            throw new RuntimeException(sprintf('File [%s] already exists.', $targetPath));
        }

        if ($targetExists && $overwrite && !@unlink($targetPath)) {
            throw new RuntimeException(sprintf('Unable to overwrite file at [%s].', $targetPath));
        }

        if (!@rename($this->path, $targetPath)) {
            if (!@copy($this->path, $targetPath) || !@unlink($this->path)) {
                throw new RuntimeException(sprintf('Unable to move [%s] to [%s].', $this->path, $targetPath));
            }
        }

        $this->path = $targetPath;
        // metadata follows the file path naturally; nothing else to do

        return $this;
    }

    public function rename(string $newName, bool $overwrite = false): static
    {
        $directory = rtrim($this->getDirectory(), DIRECTORY_SEPARATOR);
        $target = $directory . DIRECTORY_SEPARATOR . ltrim($newName, DIRECTORY_SEPARATOR);

        return $this->moveTo($target, $overwrite);
    }

    public function touch(?int $timestamp = null): static
    {
        $timestamp ??= time();
        static::ensureDirectory(dirname($this->path));

        if (!touch($this->path, $timestamp)) {
            throw new RuntimeException(sprintf('Unable to touch file [%s].', $this->path));
        }

        return $this;
    }

    public function hash(string $algorithm = 'sha256'): string
    {
        if (! $this->exists()) {
            throw new RuntimeException(sprintf('File [%s] does not exist.', $this->path));
        }

        $hash = hash_file($algorithm, $this->path);

        if ($hash === false) {
            throw new RuntimeException(sprintf('Unable to hash file [%s] with [%s].', $this->path, $algorithm));
        }

        return $hash;
    }

    public function isImage(): bool
    {
        return str_starts_with($this->getMimeType(), 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->getMimeType(), 'video/');
    }

    public function isAudio(): bool
    {
        return str_starts_with($this->getMimeType(), 'audio/');
    }

    public function isText(): bool
    {
        $mime = $this->getMimeType();

        return str_starts_with($mime, 'text/') || $mime === 'application/json';
    }

    public function isPdf(): bool
    {
        return $this->getMimeType() === 'application/pdf' || strtolower($this->getExtension()) === 'pdf';
    }

    public function is(string ...$types): bool
    {
        $mime = strtolower($this->getMimeType());
        $extension = strtolower($this->getExtension());

        foreach ($types as $type) {
            $normalized = strtolower($type);

            if ($normalized === $mime || $normalized === $extension) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetch persisted metadata for the file (empty array when none stored).
     *
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * Replace the metadata payload for this file.
     *
     * @param array<string, mixed> $metadata
     */
    public function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Merge the provided key/value pairs into the existing metadata.
     *
     * @param array<string, mixed> $metadata
     */
    public function mergeMetadata(array $metadata): static
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    public function forgetMetadata(string $key): static
    {
        if (array_key_exists($key, $this->metadata)) {
            unset($this->metadata[$key]);
        }

        return $this;
    }

    /**
     * Override the detected MIME type by storing it in metadata.
     */
    public function setMimeType(string $mime): static
    {
        $this->metadata['_override_mime'] = $mime;

        return $this;
    }

    /**
     * Rename the file with a new extension (preserving the base name).
     */
    public function setExtension(string $extension): static
    {
        $extension = ltrim($extension, '.');
        $filename = $this->getFilename();
        $newName = $filename . ($extension !== '' ? '.' . $extension : '');

        return $this->rename($newName, true);
    }

    public function getSignedUrl(string|int|\DateTimeInterface $expiration = '+5 minutes', ?string $disk = null): string
    {
        $targetDisk = $disk ?? $this->disk;

        if ($targetDisk === null) {
            throw new RuntimeException('Unable to generate signed URL without a storage disk context.');
        }

        if (is_string($expiration) && !ctype_digit($expiration)) {
            $expiration = new \DateTimeImmutable($expiration);
        }

        return Storage::temporaryUrl($this->resolveStoragePath(), $expiration, $targetDisk);
    }

    public function getUrl(?string $disk = null, string|int|\DateTimeInterface $expiration = '+5 minutes'): string
    {
        $targetDisk = $disk ?? $this->disk;

        if ($targetDisk === null) {
            return $this->path;
        }

        $storagePath = $this->resolveStoragePath();
        $config = (array) config('storage.disks.' . $targetDisk, []);
        $visibility = $config['visibility'] ?? null;
        $acl = $config['acl'] ?? null;
        $isPublic = $visibility === 'public' || $acl === 'public-read' || $targetDisk === 'public';

        if ($isPublic) {
            try {
                return Storage::url($storagePath, $targetDisk);
            } catch (\Throwable) {
                // fallback to signed URL if direct URL is unavailable
            }
        }

        return $this->getSignedUrl($expiration, $targetDisk);
    }

    protected function resolveStoragePath(): string
    {
        $metadata = $this->metadata();
        $candidates = [
            '_storage_path',
            '_remote_path',
            '_remote_key',
            '_relative_path',
            'relative_path',
        ];

        foreach ($candidates as $key) {
            if (isset($metadata[$key]) && is_string($metadata[$key]) && $metadata[$key] !== '') {
                return ltrim($metadata[$key], '/');
            }
        }

        return $this->relativePath();
    }

    /**
     * Expose the underlying SplFileInfo for low-level integrations.
     */
    public function toSplFileInfo(): SplFileInfo
    {
        return new SplFileInfo($this->path);
    }

    /**
     * Ensure the target directory exists before attempting writes.
     */
    protected static function ensureDirectory(string $path): void
    {
        if ($path === '' || is_dir($path)) {
            return;
        }

        if (!@mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Unable to create directory [%s].', $path));
        }
    }

    /**
     * Determine the path relative to common storage roots (private/public).
     */
    private function relativePath(): string
    {
        $roots = [
            storage_path('app/private'),
            storage_path('app/public'),
            storage_path(),
        ];

        $trimChars = '/\\\\';

        foreach ($roots as $root) {
            $root = rtrim($root, '\\/');

            if ($root !== '' && str_starts_with($this->path, $root)) {
                return ltrim(substr($this->path, strlen($root)), $trimChars);
            }
        }

        return ltrim($this->path, $trimChars);
    }
}
