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

    /**
     * Delete one or many files. Missing files are treated as already-gone.
     *
     * @param string|array<int, string> $paths
     */
    public static function delete(string|array $paths, ?string $disk = null): bool
    {
        return static::disk($disk)->delete($paths);
    }

    /** Recursively remove a directory and everything under it. */
    public static function deleteDirectory(string $directory, ?string $disk = null): bool
    {
        return static::disk($disk)->deleteDirectory($directory);
    }

    /** Copy a file inside the same disk. Returns false when the source is missing. */
    public static function copy(string $from, string $to, ?string $disk = null): bool
    {
        return static::disk($disk)->copy($from, $to);
    }

    /** Move (rename) a file inside the same disk. */
    public static function move(string $from, string $to, ?string $disk = null): bool
    {
        return static::disk($disk)->move($from, $to);
    }

    /** Prepend $data to the file. Creates the file when it does not exist. */
    public static function prepend(string $path, string $data, ?string $disk = null): string
    {
        return static::disk($disk)->prepend($path, $data);
    }

    /** Append $data to the file. Creates the file when it does not exist. */
    public static function append(string $path, string $data, ?string $disk = null): string
    {
        return static::disk($disk)->append($path, $data);
    }

    /**
     * List immediate or recursive sub-directories.
     *
     * @return array<int, string>
     */
    public static function directories(string $directory = '', bool $recursive = false, ?string $disk = null): array
    {
        return static::disk($disk)->directories($directory, $recursive);
    }

    /** Create a directory (recursively if needed). */
    public static function makeDirectory(string $path, ?string $disk = null): bool
    {
        return static::disk($disk)->makeDirectory($path);
    }

    /** File size in bytes. */
    public static function size(string $path, ?string $disk = null): int
    {
        return static::disk($disk)->size($path);
    }

    /** Last-modified time as a Unix timestamp. */
    public static function lastModified(string $path, ?string $disk = null): int
    {
        return static::disk($disk)->lastModified($path);
    }

    /** Best-effort MIME type. Falls back to application/octet-stream. */
    public static function mimeType(string $path, ?string $disk = null): string
    {
        return static::disk($disk)->mimeType($path);
    }

    /**
     * Open the file for reading. Caller must fclose() the returned resource.
     *
     * @return resource
     */
    public static function readStream(string $path, ?string $disk = null)
    {
        return static::disk($disk)->readStream($path);
    }

    /**
     * Pipe a readable stream into the disk. Does not close the supplied stream.
     *
     * @param resource $stream
     */
    public static function writeStream(string $path, $stream, ?string $disk = null): string
    {
        return static::disk($disk)->writeStream($path, $stream);
    }

    /** Set visibility to 'public' or 'private'. Driver-specific semantics. */
    public static function setVisibility(string $path, string $visibility, ?string $disk = null): bool
    {
        return static::disk($disk)->setVisibility($path, $visibility);
    }

    /** Read the current visibility of a file. */
    public static function getVisibility(string $path, ?string $disk = null): string
    {
        return static::disk($disk)->getVisibility($path);
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
