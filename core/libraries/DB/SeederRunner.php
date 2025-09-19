<?php

declare(strict_types=1);

namespace Zero\Lib\DB;

use RuntimeException;

class SeederRunner
{
    public static function run(string $class): void
    {
        if (!class_exists($class)) {
            $path = self::resolveSeederPath($class);
            if ($path !== null && file_exists($path)) {
                require_once $path;
            }
        }

        if (!class_exists($class)) {
            throw new RuntimeException("Seeder {$class} not found.");
        }

        $instance = new $class();

        if (! $instance instanceof Seeder) {
            throw new RuntimeException("Seeder {$class} must extend " . Seeder::class);
        }

        $instance->run();
    }

    protected static function resolveSeederPath(string $class): ?string
    {
        $relative = $class;

        if (strpos($class, 'Database\\') === 0) {
            $relative = substr($class, strlen('Database\\'));
        }

        $segments = explode('\\', $relative);
        if (empty($segments)) {
            return null;
        }

        if (count($segments) === 1) {
            return base('database/seeders/' . $segments[0] . '.php');
        }

        $directory = strtolower(array_shift($segments));
        $path = 'database/' . $directory . '/' . implode('/', $segments) . '.php';

        return base($path);
    }
}
