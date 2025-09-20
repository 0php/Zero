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
            fwrite(STDOUT, "No tables detected to drop.\n");
        } else {
            foreach ($dropped as $table) {
                fwrite(STDOUT, "Dropped table: {$table}\n");
            }
        }

        // Recreate the migrations repository and rerun migrations from scratch.
        $migrator = Migrations::makeMigrator();
        $executed = $migrator->run();

        if (empty($executed)) {
            fwrite(STDOUT, "No migrations were run.\n");
        } else {
            foreach ($executed as $name) {
                fwrite(STDOUT, "Migrated: {$name}\n");
            }
        }

        return 0;
    }
}

