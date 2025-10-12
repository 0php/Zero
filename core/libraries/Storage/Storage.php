<?php

declare(strict_types=1);

namespace Zero\Lib\Storage;

use Zero\Lib\Filesystem\File;

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

    /**
     * Write raw contents or a File instance to the target disk path.
     */
    public static function put(string $path, string|\Zero\Lib\Filesystem\File $contents, ?string $disk = null): string
    {
        return static::disk($disk)->put($path, $contents);
    }

    public static function putFile(string $path, File $file, ?string $disk = null): string
    {
        return static::disk($disk)->putFile($path, $file);
    }

    public static function putFileAs(string $path, File $file, string $name, ?string $disk = null): string
    {
        return static::disk($disk)->putFileAs($path, $file, $name);
    }

    /**
     * Retrieve the contents of a stored file (supports path or File instance).
     */
    public static function get(string|\Zero\Lib\Filesystem\File $path, ?string $disk = null): string
    {
        return static::disk($disk)->get($path);
    }

    public static function exists(string $path, ?string $disk = null): bool
    {
        return static::disk($disk)->exists($path);
    }

    public static function files(string $directory = '', bool $recursive = false, ?string $disk = null): array
    {
        return static::disk($disk)->files($directory, $recursive);
    }

    public static function url(string $path, ?string $disk = null): string
    {
        return static::disk($disk)->url($path);
    }

    public static function temporaryUrl(string $path, \DateTimeInterface|int $expiration, ?string $disk = null): string
    {
        return static::disk($disk)->temporaryUrl($path, $expiration);
    }

    public static function response(string $path, ?string $disk = null, array $options = []): \Zero\Lib\Http\Response
    {
        return static::disk($disk)->response($path, $options);
    }

    public static function __callStatic(string $name, array $arguments)
    {
        if ($name === 'list') {
            $directory = $arguments[0] ?? '';
            $recursive = false;
            $disk = null;

            if (array_key_exists(1, $arguments)) {
                $second = $arguments[1];

                if (is_bool($second)) {
                    $recursive = $second;
                } elseif (is_string($second)) {
                    $disk = $second;
                }
            }

            if (array_key_exists(2, $arguments)) {
                $disk = $arguments[2];
            }

            return static::files($directory, $recursive, $disk);
        }

        throw new \BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $name));
    }
}
