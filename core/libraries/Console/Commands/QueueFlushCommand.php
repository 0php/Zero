<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Database;

final class QueueFlushCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'queue:flush';
    }

    public function getDescription(): string
    {
        return 'Delete every entry from failed_jobs.';
    }

    public function getUsage(): string
    {
        return 'php zero queue:flush';
    }

    public function execute(array $argv): int
    {
        $table = $this->failedTable();

        $deleted = Database::query(sprintf('DELETE FROM %s', $table), null, [], 'delete');

        fwrite(STDOUT, sprintf("Flushed %d failed job(s).\n", (int) $deleted));

        return 0;
    }

    private function failedTable(): string
    {
        $config = config('queue.failed');
        return is_array($config) && isset($config['table']) ? (string) $config['table'] : 'failed_jobs';
    }
}
