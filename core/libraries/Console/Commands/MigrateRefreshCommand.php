<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Console\Support\Migrations;

final class MigrateRefreshCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'migrate:refresh';
    }

    public function getDescription(): string
    {
        return 'Rollback all migrations and run them again';
    }

    public function getUsage(): string
    {
        return 'php zero migrate:refresh';
    }

    public function execute(array $argv): int
    {
        $migrator = Migrations::makeMigrator();
        $rolled = $migrator->reset();

        if (empty($rolled)) {
            fwrite(STDOUT, "Nothing to rollback.\n");
        } else {
            foreach ($rolled as $name) {
                fwrite(STDOUT, "Rolled back: {$name}\n");
            }
        }

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

