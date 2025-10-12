<?php
declare(strict_types=1);

namespace Zero\Lib\Http;

use RuntimeException;
use Zero\Lib\Filesystem\File;
use Zero\Lib\Storage\Storage;

final class UploadedFile extends File
{
    public function __construct(
        string $tempPath,
        private string $originalName,
        private string $mimeType,
        private int $size,
        private int $error,
        array $metadata = []
    ) {
        parent::__construct($tempPath, false, $metadata);
    }

    public function getClientOriginalName(): string
    {
        return $this->originalName;
    }

    public function getClientMimeType(): string
    {
        return $this->mimeType;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && is_uploaded_file($this->path);
    }

    public function store(string $directory, ?string $disk = null): string
    {
        $this->ensureDefaultMetadata();

        return Storage::putFile($directory, $this, $disk);
    }

    public function storeAs(string $directory, string $name, ?string $disk = null): string
    {
        $this->ensureDefaultMetadata();

        return Storage::putFileAs($directory, $this, $name, $disk);
    }

    public function move(string $destinationPath, bool $overwrite = false): void
    {
        $targetExists = is_file($destinationPath);

        if ($targetExists && !$overwrite) {
            throw new RuntimeException(sprintf('File [%s] already exists.', $destinationPath));
        }

        if ($targetExists && $overwrite && !@unlink($destinationPath)) {
            throw new RuntimeException(sprintf('Unable to overwrite file at [%s].', $destinationPath));
        }

        self::ensureDirectory(dirname($destinationPath));

        if (!move_uploaded_file($this->path, $destinationPath)) {
            $this->moveTo($destinationPath, $overwrite);
            return;
        }
        $this->path = $destinationPath;
    }

    public function getClientExtension(): string
    {
        return pathinfo($this->originalName, PATHINFO_EXTENSION);
    }

    public function getSafeName(): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]+/', '-', $this->getClientFilename()) ?: 'file';
    }

    public function getClientFilename(): string
    {
        return pathinfo($this->originalName, PATHINFO_FILENAME);
    }

    public function getExtension(): string
    {
        $extension = parent::getExtension();

        if ($extension === '') {
            $extension = $this->getClientExtension();
        }

        return strtolower($extension);
    }

    /**
     * Build a hashed filename (including extension) for the upload.
     *
     * The base name is the SHA-1 hash of the temporary file plus a timestamp,
     * ensuring that identical uploads can coexist without collisions.
     *
     * @param string|null $directory Optional directory prefix (without trailing slash)
     * @param bool $ensureUnique Unused (kept for backwards compatibility)
     * @param string|null $disk Optional disk name (ignored, hashed name is already unique)
     */
    public function hashedName(?string $directory = null, bool $ensureUnique = false, ?string $disk = null): string
    {
        $extension = $this->getExtension();
        $hash = $this->hash() . '-' . time() . '-' . bin2hex(random_bytes(4));
        $name = $hash . ($extension !== '' ? '.' . $extension : '');

        $baseDir = $directory === null || $directory === '' ? '' : trim($directory, '/\\');
        $path = $baseDir === '' ? $name : $baseDir . '/' . $name;

        if ($ensureUnique || ($disk !== null && Storage::disk($disk)->exists($path))) {
            $path = $this->generateUniqueHashedName($hash, $extension, $baseDir, $disk);
        }

        return $path;
    }

    /**
     * @deprecated The hashed name already includes a timestamp; left in place for edge cases where uniqueness must be re-checked.
     */
    private function generateUniqueHashedName(string $hash, string $extension, string $baseDir, ?string $disk): string
    {
        $suffix = $this->randomSuffix();
        $name = $hash . $suffix . ($extension !== '' ? '.' . $extension : '');
        $path = $baseDir === '' ? $name : $baseDir . '/' . $name;

        while ($disk !== null && Storage::disk($disk)->exists($path)) {
            $suffix = $this->randomSuffix();
            $name = $hash . $suffix . ($extension !== '' ? '.' . $extension : '');
            $path = $baseDir === '' ? $name : $baseDir . '/' . $name;
        }

        return $path;
    }

    /**
     * Generate a random suffix for legacy uniqueness handling.
     */
    private function randomSuffix(): string
    {
        try {
            return '-' . bin2hex(random_bytes(4));
        } catch (\Throwable) {
            return '-' . uniqid();
        }
    }

    private function ensureDefaultMetadata(): void
    {
        $existing = $this->metadata();

        if (! array_key_exists('original_name', $existing)) {
            $existing['original_name'] = $this->getClientOriginalName();
        }

        $this->setMetadata($existing);
    }

    public function getSize(): int
    {
        if ($this->size > 0) {
            return $this->size;
        }

        return parent::getSize();
    }
}
