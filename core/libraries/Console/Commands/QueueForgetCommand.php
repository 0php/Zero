<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Database;

final class QueueForgetCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'queue:forget';
    }

    public function getDescription(): string
    {
        return 'Delete a single failed job by id.';
    }

    public function getUsage(): string
    {
        return 'php zero queue:forget {id}';
    }

    public function execute(array $argv): int
    {
        $id = $argv[2] ?? null;

        if ($id === null || ! ctype_digit((string) $id)) {
            fwrite(STDERR, "Usage: {$this->getUsage()}\n");

            return 1;
        }

        $table = $this->failedTable();

        $deleted = Database::query(sprintf('DELETE FROM %s WHERE id = ?', $table), null, [(int) $id], 'delete');

        fwrite(STDOUT, $deleted > 0 ? "Forgot failed job #{$id}.\n" : "No failed job with id {$id}.\n");

        return 0;
    }

    private function failedTable(): string
    {
        $config = config('queue.failed');
        return is_array($config) && isset($config['table']) ? (string) $config['table'] : 'failed_jobs';
    }
}
