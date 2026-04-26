<?php

declare(strict_types=1);

namespace Zero\Lib\Queue;

use InvalidArgumentException;
use Zero\Lib\Queue\Drivers\DatabaseDriver;
use Zero\Lib\Queue\Drivers\DriverInterface;
use Zero\Lib\Queue\Drivers\SyncDriver;

/**
 * Resolves connection names to driver instances and caches them per-name.
 *
 * Reads config/queue.php once on first access. Connections are reusable;
 * tests that swap config should call reset() first.
 */
final class QueueManager
{
    /** @var array<string, DriverInterface> */
    private static array $drivers = [];

    private static ?array $config = null;

    public static function driver(?string $name = null): DriverInterface
    {
        $name ??= self::config()['default'] ?? 'sync';

        if (isset(self::$drivers[$name])) {
            return self::$drivers[$name];
        }

        $connections = self::config()['connections'] ?? [];
        if (! isset($connections[$name]) || ! is_array($connections[$name])) {
            throw new InvalidArgumentException(sprintf('Queue connection "%s" is not configured.', $name));
        }

        $config = $connections[$name];
        $driver = $config['driver'] ?? null;

        $instance = match ($driver) {
            'sync' => new SyncDriver(),
            'database' => new DatabaseDriver($config + ['name' => $name]),
            default => throw new InvalidArgumentException(sprintf('Unsupported queue driver "%s".', (string) $driver)),
        };

        return self::$drivers[$name] = $instance;
    }

    public static function defaultConnection(): string
    {
        return self::config()['default'] ?? 'sync';
    }

    public static function defaultQueue(?string $connection = null): string
    {
        $name = $connection ?? self::defaultConnection();
        $connections = self::config()['connections'] ?? [];

        return $connections[$name]['queue'] ?? 'default';
    }

    /**
     * @return array<string, mixed>
     */
    public static function connectionConfig(?string $connection = null): array
    {
        $name = $connection ?? self::defaultConnection();
        $connections = self::config()['connections'] ?? [];

        return is_array($connections[$name] ?? null) ? $connections[$name] : [];
    }

    public static function reset(): void
    {
        self::$drivers = [];
        self::$config = null;
    }

    /**
     * Override the resolved driver for a connection. Test-only convenience.
     */
    public static function setDriver(string $name, DriverInterface $driver): void
    {
        self::$drivers[$name] = $driver;
    }

    /**
     * @return array<string, mixed>
     */
    private static function config(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        if (function_exists('config')) {
            $config = config('queue');
            if (is_array($config)) {
                return self::$config = $config;
            }
        }

        return self::$config = [
            'default' => 'sync',
            'connections' => ['sync' => ['driver' => 'sync']],
        ];
    }
}
