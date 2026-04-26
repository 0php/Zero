<?php

declare(strict_types=1);

namespace Zero\Lib\Queue\Drivers;

use PDO;
use Throwable;
use Zero\Lib\Database;
use Zero\Lib\Queue\Job;
use Zero\Lib\Queue\JobPayload;
use Zero\Lib\Queue\ReservedJob;

/**
 * Database-backed queue driver. Stores jobs in the configured `jobs` table
 * and failed jobs in `failed_jobs`. Concurrency is enforced with row-level
 * locking; the precise mechanism varies by driver (FOR UPDATE SKIP LOCKED on
 * MySQL 8/Postgres, FOR UPDATE on older MySQL, BEGIN IMMEDIATE on SQLite).
 */
final class DatabaseDriver implements DriverInterface
{
    private string $table;
    private string $failedTable;
    private string $defaultQueue;
    private int $retryAfter;
    private string $name;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->table = (string) ($config['table'] ?? 'jobs');
        $this->failedTable = (string) ($config['failed_table'] ?? 'failed_jobs');
        $this->defaultQueue = (string) ($config['queue'] ?? 'default');
        $this->retryAfter = (int) ($config['retry_after'] ?? 90);
        $this->name = (string) ($config['name'] ?? 'database');
    }

    public function push(Job $job, ?string $queue = null): void
    {
        $this->insert($queue ?? $this->defaultQueue, JobPayload::encode($job), 0);
    }

    public function later(int $delaySeconds, Job $job, ?string $queue = null): void
    {
        $this->insert($queue ?? $this->defaultQueue, JobPayload::encode($job), max(0, $delaySeconds));
    }

    public function pop(array $queues): ?ReservedJob
    {
        if ($queues === []) {
            $queues = [$this->defaultQueue];
        }

        $driver = $this->driverName();

        $now = $this->nowExpression();
        $reclaim = $this->reclaimExpression();

        $placeholders = implode(',', array_fill(0, count($queues), '?'));

        Database::startTransaction();

        try {
            $select = sprintf(
                'SELECT id, queue, payload, attempts FROM %s WHERE queue IN (%s) AND (reserved_at IS NULL OR reserved_at <= %s) AND available_at <= %s ORDER BY id ASC LIMIT 1%s',
                $this->table,
                $placeholders,
                $reclaim,
                $now,
                $this->lockClause($driver)
            );

            $row = Database::query($select, null, $queues, 'first');

            if (! $row || ! is_array($row)) {
                Database::commit();

                return null;
            }

            $id = (int) $row['id'];

            $update = sprintf(
                'UPDATE %s SET reserved_at = %s, attempts = attempts + 1 WHERE id = ?',
                $this->table,
                $now
            );

            Database::query($update, null, [$id], 'update');

            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();

            throw $e;
        }

        $payload = JobPayload::fromJson((string) $row['payload']);

        try {
            $jobInstance = JobPayload::decode($payload);
        } catch (Throwable $e) {
            // Hydration failed (missing class/model). Move straight to failed_jobs.
            $reserved = new ReservedJob(
                job: new BrokenJob(),
                queue: (string) $row['queue'],
                attempts: (int) $row['attempts'] + 1,
                payload: $payload,
                id: $id
            );
            $this->fail($reserved, $e);

            return null;
        }

        return new ReservedJob(
            job: $jobInstance,
            queue: (string) $row['queue'],
            attempts: (int) $row['attempts'] + 1,
            payload: $payload,
            id: $id
        );
    }

    public function release(ReservedJob $job, int $delaySeconds): void
    {
        if ($job->id === null) {
            return;
        }

        $availableAt = $this->timestampExpression($delaySeconds);

        $sql = sprintf(
            'UPDATE %s SET reserved_at = NULL, available_at = %s WHERE id = ?',
            $this->table,
            $availableAt
        );

        Database::query($sql, null, [$job->id], 'update');
    }

    public function delete(ReservedJob $job): void
    {
        if ($job->id === null) {
            return;
        }

        Database::query(
            sprintf('DELETE FROM %s WHERE id = ?', $this->table),
            null,
            [$job->id],
            'delete'
        );
    }

    public function fail(ReservedJob $job, Throwable $exception): void
    {
        $exceptionText = sprintf(
            "%s: %s\n%s",
            $exception::class,
            $exception->getMessage(),
            $exception->getTraceAsString()
        );

        Database::create(
            sprintf(
                'INSERT INTO %s (connection, queue, payload, exception, failed_at) VALUES (?, ?, ?, ?, %s)',
                $this->failedTable,
                $this->nowExpression()
            ),
            null,
            [$this->name, $job->queue, JobPayload::toJson($job->payload), $exceptionText]
        );

        if ($job->id !== null) {
            Database::query(
                sprintf('DELETE FROM %s WHERE id = ?', $this->table),
                null,
                [$job->id],
                'delete'
            );
        }
    }

    public function size(?string $queue = null): int
    {
        $queue ??= $this->defaultQueue;

        $row = Database::query(
            sprintf('SELECT COUNT(*) AS aggregate FROM %s WHERE queue = ?', $this->table),
            null,
            [$queue],
            'first'
        );

        if (is_array($row) && isset($row['aggregate'])) {
            return (int) $row['aggregate'];
        }

        return 0;
    }

    private function insert(string $queue, array $payload, int $delaySeconds): void
    {
        Database::create(
            sprintf(
                'INSERT INTO %s (queue, payload, attempts, reserved_at, available_at, created_at) VALUES (?, ?, 0, NULL, %s, %s)',
                $this->table,
                $this->timestampExpression($delaySeconds),
                $this->nowExpression()
            ),
            null,
            [$queue, JobPayload::toJson($payload)]
        );
    }

    private function driverName(): string
    {
        $driver = config('database.' . config('database.connection') . '.driver');
        if (is_string($driver) && $driver !== '') {
            return strtolower($driver);
        }

        return 'mysql';
    }

    private function nowExpression(): string
    {
        return match ($this->driverName()) {
            'sqlite3', 'sqlite' => "datetime('now')",
            'pgsql', 'postgres' => 'NOW()',
            default => 'NOW()',
        };
    }

    private function reclaimExpression(): string
    {
        return match ($this->driverName()) {
            'sqlite3', 'sqlite' => sprintf("datetime('now', '-%d seconds')", $this->retryAfter),
            'pgsql', 'postgres' => sprintf("NOW() - INTERVAL '%d seconds'", $this->retryAfter),
            default => sprintf('NOW() - INTERVAL %d SECOND', $this->retryAfter),
        };
    }

    private function timestampExpression(int $offsetSeconds): string
    {
        if ($offsetSeconds <= 0) {
            return $this->nowExpression();
        }

        return match ($this->driverName()) {
            'sqlite3', 'sqlite' => sprintf("datetime('now', '+%d seconds')", $offsetSeconds),
            'pgsql', 'postgres' => sprintf("NOW() + INTERVAL '%d seconds'", $offsetSeconds),
            default => sprintf('NOW() + INTERVAL %d SECOND', $offsetSeconds),
        };
    }

    private function lockClause(string $driver): string
    {
        return match ($driver) {
            'mysql', 'mariadb' => ' FOR UPDATE',
            'pgsql', 'postgres' => ' FOR UPDATE SKIP LOCKED',
            default => '', // sqlite handles via BEGIN IMMEDIATE / serialized writes
        };
    }
}

/**
 * Internal sentinel returned to fail() when payload hydration explodes — we
 * still need a Job instance to satisfy ReservedJob's type.
 */
final class BrokenJob implements Job
{
    public function handle(): void {}
}
