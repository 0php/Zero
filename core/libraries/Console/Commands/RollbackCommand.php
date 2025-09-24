<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Console\Support\Migrations;

final class RollbackCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'migrate:rollback';
    }

    public function getDescription(): string
    {
        return 'Rollback the latest database migrations';
    }

    public function getUsage(): string
    {
        return 'php zero migrate:rollback [steps]';
    }

    public function execute(array $argv): int
    {
        $steps = isset($argv[2]) ? max(1, (int) $argv[2]) : 1;
        $migrator = Migrations::makeMigrator();
        $rolled = $migrator->rollback($steps);

        if (empty($rolled)) {
            \Zero\Lib\Log::channel('internal')->info('Nothing to rollback.');

            return 0;
        }

        foreach ($rolled as $name) {
            \Zero\Lib\Log::channel('internal')->info("Rolled back: {$name}");
        }

        return 0;
    }
}
