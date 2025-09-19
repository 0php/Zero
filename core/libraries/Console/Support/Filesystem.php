<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Support;

use RuntimeException;

final class Filesystem
{
    public static function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create directory: {$directory}");
        }
    }
}
