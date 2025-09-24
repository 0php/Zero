<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Console\Support\DatabaseCleaner;
use Zero\Lib\Console\Support\Migrations;

final class MigrateFreshCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'migrate:fresh';
    }

    public function getDescription(): string
    {
        return 'Drop all tables and run all migrations from scratch';
    }

    public function getUsage(): string
    {
        return 'php zero migrate:fresh';
    }

    public function execute(array $argv): int
    {
        $dropped = DatabaseCleaner::dropAllTables();

        if (empty($dropped)) {
            \Zero\Lib\Log::channel('internal')->info('No tables detected to drop.');
        } else {
            foreach ($dropped as $table) {
                \Zero\Lib\Log::channel('internal')->info("Dropped table: {$table}");
            }
        }

        // Recreate the migrations repository and rerun migrations from scratch.
        $migrator = Migrations::makeMigrator();
        $executed = $migrator->run();

        if (empty($executed)) {
            \Zero\Lib\Log::channel('internal')->info('No migrations were run.');
        } else {
            foreach ($executed as $name) {
                \Zero\Lib\Log::channel('internal')->info("Migrated: {$name}");
            }
        }

        return 0;
    }
}
