<?php

declare(strict_types=1);

namespace Zero\Lib\Queue;

use Zero\Lib\Queue\Drivers\DriverInterface;

/**
 * Static facade over QueueManager + the resolved driver. Mirrors the shape
 * other Zero facades use (Auth, Mail, Storage, etc.).
 */
final class Queue
{
    public static function push(Job $job, ?string $queue = null, ?string $connection = null): void
    {
        self::driver($connection)->push($job, $queue ?? QueueManager::defaultQueue($connection));
    }

    public static function later(int $delaySeconds, Job $job, ?string $queue = null, ?string $connection = null): void
    {
        self::driver($connection)->later($delaySeconds, $job, $queue ?? QueueManager::defaultQueue($connection));
    }

    public static function size(?string $queue = null, ?string $connection = null): int
    {
        return self::driver($connection)->size($queue ?? QueueManager::defaultQueue($connection));
    }

    public static function driver(?string $connection = null): DriverInterface
    {
        return QueueManager::driver($connection);
    }
}
