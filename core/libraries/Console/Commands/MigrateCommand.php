<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Console\Support\Migrations;

final class MigrateCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'migrate';
    }

    public function getDescription(): string
    {
        return 'Run pending database migrations';
    }

    public function getUsage(): string
    {
        return 'php zero migrate';
    }

    public function execute(array $argv): int
    {
        $migrator = Migrations::makeMigrator();
        $executed = $migrator->run();

        if (empty($executed)) {
            fwrite(STDOUT, "No pending migrations.\n");

            return 0;
        }

        foreach ($executed as $name) {
            fwrite(STDOUT, "Migrated: {$name}\n");
        }

        return 0;
    }
}
