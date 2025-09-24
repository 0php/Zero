<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\DB\SeederRunner;

final class SeedCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'db:seed';
    }

    public function getDescription(): string
    {
        return 'Execute a database seeder class';
    }

    public function getUsage(): string
    {
        return 'php zero db:seed [FQN]';
    }

    public function execute(array $argv): int
    {
        $class = $argv[2] ?? 'Database\\Seeders\\DatabaseSeeder';

        SeederRunner::run($class);

        return 0;
    }
}
