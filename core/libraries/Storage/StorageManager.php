<?php

declare(strict_types=1);

namespace Zero\Lib\Storage;

use InvalidArgumentException;
use Zero\Lib\Storage\Drivers\LocalStorage;
use Zero\Lib\Storage\Drivers\S3Storage;

use function env;
use function storage_path;

final class StorageManager
{
    private string $defaultDisk;

    /** @var array<string, mixed> */
    private array $disks;

    /** @var array<string, object> */
    private array $resolved = [];

    public function __construct()
    {
        $config = config('storage');

        $this->defaultDisk = $config['default'] ?? 'public';
        $this->disks = $config['disks'] ?? [
            'public' => [
                'driver' => 'local',
                'root' => storage_path('app/public'),
                'url' => (static function (): string {
                    $appUrl = rtrim((string) env('APP_URL', ''), '/');

                    return $appUrl === '' ? '' : $appUrl . '/storage';
                })(),
            ],
            'private' => [
                'driver' => 'local',
                'root' => storage_path('app/private'),
            ],
        ];
    }

    public function disk(?string $name = null): object
    {
        $name ??= $this->defaultDisk;

        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        if (! isset($this->disks[$name])) {
            throw new InvalidArgumentException(sprintf('Storage disk [%s] is not defined.', $name));
        }

        $driver = $this->disks[$name]['driver'] ?? 'local';

        return $this->resolved[$name] = $this->createDriver($name, $driver, $this->disks[$name]);
    }

    private function createDriver(string $name, string $driver, array $config): object
    {
        return match ($driver) {
            'local' => new LocalStorage($config, $name),
            's3' => new S3Storage($config, $name),
            default => throw new InvalidArgumentException(sprintf('Unsupported storage driver [%s].', $driver)),
        };
    }

    public function getDiskConfig(string $disk): array
    {
        if (! isset($this->disks[$disk])) {
            throw new InvalidArgumentException(sprintf('Storage disk [%s] is not defined.', $disk));
        }

        return $this->disks[$disk];
    }
}
