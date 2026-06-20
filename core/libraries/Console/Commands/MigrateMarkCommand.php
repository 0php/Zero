<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\DB\MigrationRepository;

/**
 * Mark migration files as already-ran without executing them. Useful when
 * porting a legacy database where the target schema already exists.
 *
 * Usage:
 *   php zero migrate:mark <migration_filename_without_extension>
 *   php zero migrate:mark --all       # mark every file under database/migrations
 */
final class MigrateMarkCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'migrate:mark';
    }

    public function getDescription(): string
    {
        return 'Mark migration files as ran without executing them.';
    }

    public function getUsage(): string
    {
        return 'php zero migrate:mark <name>|--all';
    }

    public function execute(array $argv): int
    {
        $arg = $argv[2] ?? null;
        if ($arg === null) {
            fwrite(STDERR, "Usage: " . $this->getUsage() . "\n");
            return 1;
        }

        $repository = new MigrationRepository();
        $ran = $repository->getRan();
        $batch = $repository->getNextBatchNumber();

        $targets = [];

        if ($arg === '--all') {
            foreach (glob(base('database/migrations/*.php')) ?: [] as $path) {
                $name = basename($path, '.php');
                if (!in_array($name, $ran, true)) {
                    $targets[] = $name;
                }
            }
        } else {
            $name = preg_replace('/\.php$/', '', basename($arg));
            if (in_array($name, $ran, true)) {
                echo "Already marked: {$name}\n";
                return 0;
            }
            $targets[] = $name;
        }

        if ($targets === []) {
            echo "Nothing to mark.\n";
            return 0;
        }

        foreach ($targets as $name) {
            $repository->log($name, $batch);
            echo "Marked: {$name}\n";
        }

        return 0;
    }
}
