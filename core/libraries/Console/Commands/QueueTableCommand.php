<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Zero\Lib\Console\Command\CommandInterface;

/**
 * Verifies that the queue migration files are in place. The framework ships
 * the migrations under database/migrations; this command exists so operators
 * can confirm a fresh checkout is ready to run `php zero migrate`.
 */
final class QueueTableCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'queue:table';
    }

    public function getDescription(): string
    {
        return 'Confirm the jobs and failed_jobs migrations are present.';
    }

    public function getUsage(): string
    {
        return 'php zero queue:table';
    }

    public function execute(array $argv): int
    {
        $directory = base('database/migrations');

        if (! is_dir($directory)) {
            fwrite(STDERR, "Migrations directory not found: {$directory}\n");

            return 1;
        }

        $files = glob($directory . DIRECTORY_SEPARATOR . '*.php') ?: [];
        $found = [
            'jobs.php' => null,
            'failed_jobs.php' => null,
        ];

        foreach ($files as $file) {
            $base = basename($file);
            if (str_ends_with($base, 'failed_jobs.php')) {
                $found['failed_jobs.php'] ??= $base;
            } elseif (str_ends_with($base, 'jobs.php')) {
                $found['jobs.php'] ??= $base;
            }
        }

        $missing = [];
        foreach ($found as $label => $match) {
            if ($match === null) {
                $missing[] = $label;
            } else {
                fwrite(STDOUT, sprintf("Found migration for %s: %s\n", $label, $match));
            }
        }

        if ($missing !== []) {
            fwrite(STDERR, "Missing migrations: " . implode(', ', $missing) . "\n");
            fwrite(STDERR, "Re-publish them from the framework or run `php zero make:migration ...` manually.\n");

            return 1;
        }

        fwrite(STDOUT, "Run `php zero migrate` to create the queue tables.\n");

        return 0;
    }
}
