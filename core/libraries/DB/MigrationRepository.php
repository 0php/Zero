<?php

declare(strict_types=1);

namespace Zero\Lib\DB;

use Zero\Lib\Database;

/**
 * Stores information about executed migrations inside the database.
 */
class MigrationRepository
{
    protected string $table = 'migrations';

    public function __construct()
    {
        $this->ensureTableExists();
    }

    /**
     * Return a flat list of executed migration names ordered by batch.
     */
    public function getRan(): array
    {
        $rows = Database::fetch(sprintf('SELECT migration FROM %s ORDER BY batch ASC, id ASC', $this->table));

        return array_map(fn ($row) => $row['migration'] ?? '', $rows);
    }

    /**
     * Determine the batch number for the next set of migrations.
     */
    public function getNextBatchNumber(): int
    {
        $row = Database::first(sprintf('SELECT MAX(batch) AS batch FROM %s', $this->table));

        return (int) (($row['batch'] ?? 0) + 1);
    }

    /**
     * Retrieve migrations for the most recent batches (used during rollback).
     */
    public function getMigrationsForRollback(int $steps): array
    {
        $rows = Database::fetch(sprintf('SELECT migration, batch FROM %s ORDER BY batch DESC, id DESC', $this->table));

        $uniqueBatches = [];
        foreach ($rows as $row) {
            $batch = (int) ($row['batch'] ?? 0);
            if (!in_array($batch, $uniqueBatches, true)) {
                $uniqueBatches[] = $batch;
            }
        }

        $targetBatches = array_slice($uniqueBatches, 0, $steps);

        return array_values(array_filter($rows, function ($row) use ($targetBatches) {
            return in_array((int) ($row['batch'] ?? 0), $targetBatches, true);
        }));
    }

    /**
     * Persist a migration entry with the given batch number.
     */
    public function log(string $migration, int $batch): void
    {
        Database::query(
            sprintf('INSERT INTO %s (migration, batch) VALUES (?, ?)', $this->table),
            null,
            [$migration, $batch],
            'create'
        );
    }

    /**
     * Delete a stored migration record.
     */
    public function delete(string $migration): void
    {
        Database::query(
            sprintf('DELETE FROM %s WHERE migration = ?', $this->table),
            null,
            [$migration],
            'delete'
        );
    }

    protected function ensureTableExists(): void
    {
        $connection = config('database.connection');
        $connectionConfig = is_string($connection) ? (array) config('database.' . $connection) : [];
        $driver = strtolower((string) ($connectionConfig['driver'] ?? 'mysql'));

        switch ($driver) {
            case 'sqlite':
            case 'sqlite3':
                $sql = sprintf(
                    'CREATE TABLE IF NOT EXISTS %s (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        migration TEXT NOT NULL,
                        batch INTEGER NOT NULL,
                        ran_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    )',
                    $this->table
                );
                break;
            case 'pgsql':
            case 'postgres':
            case 'postgresql':
                $sql = sprintf(
                    'CREATE TABLE IF NOT EXISTS %s (
                        id BIGSERIAL PRIMARY KEY,
                        migration VARCHAR(255) NOT NULL,
                        batch INT NOT NULL,
                        ran_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
                    )',
                    $this->table
                );
                break;
            default:
                $sql = sprintf(
                    'CREATE TABLE IF NOT EXISTS %s (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        migration VARCHAR(255) NOT NULL,
                        batch INT NOT NULL,
                        ran_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                    $this->table
                );
                break;
        }

        Database::query($sql);
    }
}
