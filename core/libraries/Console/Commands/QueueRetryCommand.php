<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Commands;

use Throwable;
use Zero\Lib\Console\Command\CommandInterface;
use Zero\Lib\Database;
use Zero\Lib\Queue\QueueManager;

final class QueueRetryCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'queue:retry';
    }

    public function getDescription(): string
    {
        return 'Re-push a failed job back onto its original queue.';
    }

    public function getUsage(): string
    {
        return 'php zero queue:retry {id|all}';
    }

    public function execute(array $argv): int
    {
        $target = $argv[2] ?? null;

        if ($target === null) {
            fwrite(STDERR, "Usage: {$this->getUsage()}\n");

            return 1;
        }

        $failedTable = $this->failedTable();

        $rows = $target === 'all'
            ? Database::fetch(sprintf('SELECT * FROM %s', $failedTable))
            : Database::fetch(sprintf('SELECT * FROM %s WHERE id = ?', $failedTable), null, [(int) $target]);

        if (empty($rows)) {
            fwrite(STDOUT, "No failed jobs to retry.\n");

            return 0;
        }

        $jobsTable = $this->jobsTable();
        $now = $this->nowExpression();

        foreach ($rows as $row) {
            try {
                Database::create(
                    sprintf(
                        'INSERT INTO %s (queue, payload, attempts, reserved_at, available_at, created_at) VALUES (?, ?, 0, NULL, %s, %s)',
                        $jobsTable,
                        $now,
                        $now
                    ),
                    null,
                    [$row['queue'], $row['payload']]
                );

                Database::query(sprintf('DELETE FROM %s WHERE id = ?', $failedTable), null, [(int) $row['id']], 'delete');

                fwrite(STDOUT, sprintf("Retried failed job #%d on queue %s.\n", $row['id'], $row['queue']));
            } catch (Throwable $e) {
                fwrite(STDERR, sprintf("Failed to retry job #%d: %s\n", $row['id'], $e->getMessage()));
            }
        }

        return 0;
    }

    private function failedTable(): string
    {
        $config = config('queue.failed');
        return is_array($config) && isset($config['table']) ? (string) $config['table'] : 'failed_jobs';
    }

    private function jobsTable(): string
    {
        $config = QueueManager::connectionConfig('database');
        return $config['table'] ?? 'jobs';
    }

    private function nowExpression(): string
    {
        $driver = strtolower((string) config('database.' . config('database.connection') . '.driver'));

        return match ($driver) {
            'sqlite3', 'sqlite' => "datetime('now')",
            default => 'NOW()',
        };
    }
}
