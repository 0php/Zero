<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Console\Support\Filesystem;
use Zero\Lib\Support\Str;
use Zero\Lib\Template;

final class MakeModelCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:model';
    }

    public function getDescription(): string
    {
        return 'Generate an Eloquent-like model class (optionally with a migration)';
    }

    public function getUsage(): string
    {
        return 'php zero make:model Name [-m|--migration] [--force]';
    }

    public function execute(array $argv): int
    {
        $parsed = $this->parseArguments($argv);
        $name = $parsed['name'];
        $force = $parsed['force'];
        $withMigration = $parsed['migration'];

        if ($name === null) {
            \Zero\Lib\Log::channel('internal')->error("Usage: {$this->getUsage()}");

            return 1;
        }

        $className = Str::studly($name);
        $path = app_path('models/' . $className . '.php');

        if (file_exists($path) && ! $force) {
            \Zero\Lib\Log::channel('internal')->error("Model {$className} already exists. Use --force to overwrite.");

            return 1;
        }

        Filesystem::ensureDirectory(dirname($path));

        $contents = Template::render('model.tmpl', [
            'class' => $className,
        ]);

        file_put_contents($path, $contents);
        \Zero\Lib\Log::channel('internal')->info("Model created: {$path}");

        if ($withMigration) {
            $status = $this->createMigration($className, $force);

            if ($status !== 0) {
                \Zero\Lib\Log::channel('internal')->error("Failed to create migration for model {$className}.");

                return $status;
            }
        }

        return 0;
    }

    /**
     * Parse CLI arguments to extract the model name and flags.
     */
    private function parseArguments(array $argv): array
    {
        $name = null;
        $force = false;
        $migration = false;

        foreach (array_slice($argv, 2) as $argument) {
            if ($argument === '--force') {
                $force = true;
                continue;
            }

            if ($argument === '-m' || $argument === '--migration') {
                $migration = true;
                continue;
            }

            if ($name === null && str_starts_with($argument, '-') === false) {
                $name = $argument;
            }
        }

        return [
            'name' => $name,
            'force' => $force,
            'migration' => $migration,
        ];
    }

    /**
     * Generate a migration using the registered command when requested.
     */
    private function createMigration(string $className, bool $force): int
    {
        $table = Str::snake($className);

        if (! str_ends_with($table, 's')) {
            $table .= 's';
        }

        $migrationName = 'create_' . $table . '_table';

        $command = new MakeMigrationCommand();
        $arguments = ['zero', 'make:migration', $migrationName];

        if ($force) {
            $arguments[] = '--force';
        }

        return $command->execute($arguments);
    }
}
