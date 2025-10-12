<?php

declare(strict_types=1);

namespace Zero\Lib\Filesystem;

use Zero\Lib\Storage\Storage;

/**
 * Lightweight representation of a remote object that mimics the File interface
 * without requiring the contents to be cached locally.
 */
final class RemoteFile extends File
{
    private int $size;
    private ?string $etag;
    private ?string $mime;

    private function __construct(string $path, array $metadata, string $disk)
    {
        parent::__construct($path, false, $metadata);

        $this->size = (int) ($metadata['_content_length'] ?? $metadata['_size'] ?? $metadata['size'] ?? 0);
        $etag = $metadata['_etag'] ?? $metadata['etag'] ?? null;
        $this->etag = is_string($etag) ? trim($etag, '"') : null;
        $mime = $metadata['_remote_mime'] ?? $metadata['ContentType'] ?? $metadata['content_type'] ?? null;
        $this->mime = is_string($mime) && $mime !== '' ? $mime : null;
        $this->disk = $disk;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function fromMetadata(string $path, array $metadata, string $disk): self
    {
        $metadata['_storage_disk'] = $disk;

        return new self($path, $metadata, $disk);
    }

    public function exists(): bool
    {
        return true;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getMimeType(): string
    {
        if ($this->mime !== null) {
            return $this->mime;
        }

        $metadata = $this->metadata();

        if (isset($metadata['_remote_mime']) && is_string($metadata['_remote_mime']) && $metadata['_remote_mime'] !== '') {
            return $metadata['_remote_mime'];
        }

        return 'application/octet-stream';
    }

    public function hash(string $algorithm = 'sha256'): string
    {
        if ($this->etag !== null && $this->etag !== '') {
            return $this->etag;
        }

        return hash($algorithm, $this->path);
    }

    public function getUrl(?string $disk = null, string|int|\DateTimeInterface $expiration = '+5 minutes'): string
    {
        $targetDisk = $disk ?? $this->disk;

        if ($targetDisk === null) {
            return parent::getUrl($disk, $expiration);
        }

        try {
            return Storage::url($this->resolveStoragePath(), $targetDisk);
        } catch (\Throwable) {
            return $this->getSignedUrl($expiration, $targetDisk);
        }
    }

}
