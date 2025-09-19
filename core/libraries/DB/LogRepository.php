<?php

declare(strict_types=1);

namespace Zero\Lib\DB;

use Zero\Lib\Database;

class LogRepository
{
    public function __construct(
        protected string $table = 'logs'
    ) {
        $this->ensureTable();
    }

    public function store(string $level, string $message, array $context, string $timestamp): bool
    {
        try {
            Database::query(
                sprintf('INSERT INTO %s (level, message, context, created_at, updated_at) VALUES (?, ?, ?, ?, ?)', $this->table),
                null,
                [
                    $level,
                    $message,
                    json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                    $timestamp,
                    $timestamp,
                ],
                'create'
            );

            return true;
        } catch (\Throwable $e) {
            error_log('Failed to persist log entry: ' . $e->getMessage());

            return false;
        }
    }

    protected function ensureTable(): void
    {
        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS %s (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                level VARCHAR(50) NOT NULL,
                message TEXT NOT NULL,
                context TEXT NULL,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $this->table
        );

        Database::query($sql);
    }
}
