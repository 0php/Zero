<?php

declare(strict_types=1);

namespace Zero\Lib\Storage\Drivers;

use RuntimeException;
use Zero\Lib\Http\UploadedFile;

final class LocalStorage
{
    public function __construct(private array $config)
    {
    }

    public function put(string $path, string $contents): string
    {
        $fullPath = $this->fullPath($path);
        $this->ensureDirectory(dirname($fullPath));

        if (file_put_contents($fullPath, $contents) === false) {
            throw new RuntimeException(sprintf('Unable to write file at [%s].', $fullPath));
        }

        return $this->relativePath($fullPath);
    }

    public function putFile(string $directory, UploadedFile $file): string
    {
        return $this->putFileAs($directory, $file, $this->uniqueFileName($file));
    }

    public function putFileAs(string $directory, UploadedFile $file, string $name): string
    {
        $target = $this->fullPath(trim($directory, '/') . '/' . ltrim($name, '/'));
        $this->ensureDirectory(dirname($target));

        $file->move($target);

        return $this->relativePath($target);
    }

    private function uniqueFileName(UploadedFile $file): string
    {
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);

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
}
