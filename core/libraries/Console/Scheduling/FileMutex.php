<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Scheduling;

use Zero\Lib\Console\Support\Filesystem;

final class FileMutex
{
    private string $name;

    /** @var resource|null */
    private $handle = null;

    public function __construct(string $name)
    {
        $this->name = $name !== '' ? $name : 'cron-event';
    }

    public function acquire(int $expiresAfterSeconds): bool
    {
        $directory = storage_path('framework/schedule');
        Filesystem::ensureDirectory($directory);

        $path = $directory . '/' . md5($this->name) . '.lock';
        $this->handle = fopen($path, 'c+');

        if ($this->handle === false) {
            $this->handle = null;

            return false;
        }

        $expiresAfterSeconds = max(1, $expiresAfterSeconds);

        if (! flock($this->handle, LOCK_EX | LOCK_NB)) {
            $stale = false;
            $stat = fstat($this->handle);
            if ($stat !== false) {
                $age = time() - ($stat['mtime'] ?? time());
                $stale = $age >= $expiresAfterSeconds;
            }

            if ($stale && flock($this->handle, LOCK_EX)) {
                ftruncate($this->handle, 0);
                fwrite($this->handle, (string) time());

                return true;
            }

            fclose($this->handle);
            $this->handle = null;

            return false;
        }

        ftruncate($this->handle, 0);
        fwrite($this->handle, (string) time());

        return true;
    }

    public function release(): void
    {
        if ($this->handle === null) {
            return;
        }

        flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;
    }
}
