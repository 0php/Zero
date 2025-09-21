<?php

declare(strict_types=1);

namespace Zero\Lib\Http;

use RuntimeException;
use Zero\Lib\Storage\Storage;

final class UploadedFile
{
    public function __construct(
        private string $tempPath,
        private string $originalName,
        private string $mimeType,
        private int $size,
        private int $error
    ) {
    }

    public function getClientOriginalName(): string
    {
        return $this->originalName;
    }

    public function getClientMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getPath(): string
    {
        return $this->tempPath;
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && is_uploaded_file($this->tempPath);
    }

    public function store(string $path, ?string $disk = null): string
    {
        return Storage::putFile($path, $this, $disk);
    }

    public function storeAs(string $path, string $name, ?string $disk = null): string
    {
        return Storage::putFileAs($path, $this, $name, $disk);
    }

    public function move(string $destinationPath): void
    {
        $this->ensureDirectory(dirname($destinationPath));

        if (!move_uploaded_file($this->tempPath, $destinationPath)) {
            if (!@rename($this->tempPath, $destinationPath)) {
                throw new RuntimeException('Unable to move uploaded file.');
            }
        }

        $this->tempPath = $destinationPath;
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
}
