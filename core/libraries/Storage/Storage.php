<?php

declare(strict_types=1);

namespace Zero\Lib\Storage;

use Zero\Lib\Http\UploadedFile;

final class Storage
{
    private static ?StorageManager $manager = null;

    private static function manager(): StorageManager
    {
        if (static::$manager === null) {
            static::$manager = new StorageManager();
        }

        return static::$manager;
    }

    public static function disk(?string $name = null): object
    {
        return static::manager()->disk($name);
    }

    public static function put(string $path, string $contents, ?string $disk = null): string
    {
        return static::disk($disk)->put($path, $contents);
    }

    public static function putFile(string $path, UploadedFile $file, ?string $disk = null): string
    {
        return static::disk($disk)->putFile($path, $file);
    }

    public static function putFileAs(string $path, UploadedFile $file, string $name, ?string $disk = null): string
    {
        return static::disk($disk)->putFileAs($path, $file, $name);
    }
}
