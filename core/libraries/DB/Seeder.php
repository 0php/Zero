<?php

declare(strict_types=1);

namespace Zero\Lib\DB;

use Zero\Lib\Log;
use InvalidArgumentException;

abstract class Seeder
{
    /**
     * Seed the application's database.
     */
    abstract public function run(): void;

    /**
     * Execute the given seeder class or list of classes.
     *
     * @param class-string<Seeder>|Seeder|array<class-string<Seeder>|Seeder> $seeders
     */
    protected function call(array|string|Seeder $seeders): void
    {
        $list = is_array($seeders) ? $seeders : [$seeders];

        foreach ($list as $seeder) {
            $instance = $this->resolveSeeder($seeder);
            Log::channel('internal')->info("Running seeder: ".$seeder);
            $instance->run();
        }
    }

    private function resolveSeeder(string|Seeder $seeder): Seeder
    {
        if ($seeder instanceof Seeder) {
            return $seeder;
        }

        if (!class_exists($seeder)) {
            throw new InvalidArgumentException("Seeder {$seeder} not found");
        }

        $instance = new $seeder();

        if (!$instance instanceof Seeder) {
            throw new InvalidArgumentException("Class {$seeder} must extend " . self::class);
        }

        return $instance;
    }
}
