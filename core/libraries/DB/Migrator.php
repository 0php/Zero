<?php

declare(strict_types=1);

namespace Zero\Lib\DB;

use Log;
use RuntimeException;

/**
 * Executes migration files and manages rollbacks based on repository state.
 */
class Migrator
{
    public function __construct(
        protected MigrationRepository $repository,
        protected string $migrationPath
    ) {
    }

    /**
     * Run all outstanding migrations.
     */
    public function run(): array
    {
        $files = $this->migrationFiles();
        $ran = $this->repository->getRan();
        $executed = [];
        $batch = $this->repository->getNextBatchNumber();

        foreach ($files as $file) {
            $name = $this->migrationName($file);
            if (in_array($name, $ran, true)) {
                continue;
            }

            $migration = $this->resolve($file);

            Log::info('Running migration', [
                'name' => $name,
                'batch' => $batch,
            ]);

            $migration->up();
            $this->repository->log($name, $batch);
            $executed[] = $name;
        }

        if (empty($executed)) {
            Log::info('No outstanding migrations to run');
        } else {
            Log::info('Finished running migrations', [
                'count' => count($executed),
                'batch' => $batch,
            ]);
        }

        return $executed;
    }

    /**
     * Rollback the latest migration batches.
     */
    public function rollback(int $steps = 1): array
    {
        $steps = max(1, $steps);
        $migrations = $this->repository->getMigrationsForRollback($steps);
        $rolled = [];

        foreach ($migrations as $migrationInfo) {
            $name = $migrationInfo['migration'];
            $file = $this->migrationFilePath($name);
            if (!file_exists($file)) {
                continue;
            }

            $migration = $this->resolve($file);
            if (method_exists($migration, 'down')) {
                Log::info('Rolling back migration', [
                    'name' => $name,
                    'batch' => $migrationInfo['batch'] ?? null,
                ]);

                $migration->down();
            } else {
                Log::warning('Migration missing down method, skipping rollback', [
                    'name' => $name,
                ]);
            }

            $this->repository->delete($name);
            $rolled[] = $name;
        }

        if (empty($rolled)) {
            Log::info('No migrations rolled back');
        } else {
            Log::info('Finished rolling back migrations', [
                'count' => count($rolled),
            ]);
        }

        return $rolled;
    }

    /**
     * Rollback all recorded migrations.
     */
    public function reset(): array
    {
        return $this->rollback(PHP_INT_MAX);
    }

    protected function resolve(string $file): Migration
    {
        $migration = require $file;

        if ($migration instanceof Migration) {
            return $migration;
        }

        if (is_string($migration) && class_exists($migration)) {
            $instance = new $migration();
            if ($instance instanceof Migration) {
                return $instance;
            }
        }

        throw new RuntimeException("Migration file {$file} must return an instance of " . Migration::class);
    }

    /** @return string[] */
    protected function migrationFiles(): array
    {
        $files = glob($this->migrationPath . '/*.php') ?: [];
        sort($files);

        return $files;
    }

    protected function migrationName(string $file): string
    {
        return basename($file, '.php');
    }

    protected function migrationFilePath(string $name): string
    {
        return $this->migrationPath . '/' . $name . '.php';
    }
}
