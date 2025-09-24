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
            \Zero\Lib\Log::channel('internal')->info('Nothing to rollback.');
        } else {
            foreach ($rolled as $name) {
                \Zero\Lib\Log::channel('internal')->info("Rolled back: {$name}");
            }
        }

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
