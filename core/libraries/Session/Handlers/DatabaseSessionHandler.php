<?php

declare(strict_types=1);

namespace Zero\Lib\Session\Handlers;

use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;
use Zero\Lib\Database;
use Zero\Lib\Log;

class DatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private static bool $tableEnsured = false;

    public function __construct(
        private string $table,
        private int $lifetimeSeconds
    ) {
        $this->ensureTable();
    }

    public function validateId(string $id): bool
    {
        return true;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $now = date('Y-m-d H:i:s');

        try {
            Database::query(
                sprintf('UPDATE %s SET last_activity = ?, updated_at = ? WHERE id = ?', $this->table),
                null,
                [$now, $now, $id],
                'update'
            );

            return true;
        } catch (\Throwable $e) {
            Log::error('Session timestamp update failed', ['error' => $e->getMessage()]);

            return true;
        }
    }

    public function open($savePath, $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        try {
            $expiryThreshold = date('Y-m-d H:i:s', time() - $this->lifetimeSeconds);

            $row = Database::first(
                sprintf('SELECT payload FROM %s WHERE id = ? AND last_activity >= ?', $this->table),
                null,
                [$id, $expiryThreshold]
            );

            return is_array($row) ? (string) ($row['payload'] ?? '') : '';
        } catch (\Throwable $e) {
            Log::error('Session read failed', ['error' => $e->getMessage()]);
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        $now = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        try {
            // Use INSERT ... ON DUPLICATE KEY UPDATE to handle race conditions
            // This prevents duplicate key errors when multiple requests try to create the same session
            Database::query(
                sprintf('INSERT INTO %s (id, payload, last_activity, ip_address, user_agent, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE payload = VALUES(payload), last_activity = VALUES(last_activity), ip_address = VALUES(ip_address), user_agent = VALUES(user_agent), updated_at = VALUES(updated_at)', $this->table),
                null,
                [$id, $data, $now, $ip, $agent, $now, $now],
                'upsert'
            );

            return true;
        } catch (\Throwable $e) {
            Log::error('Session write failed', ['error' => $e->getMessage()]);

            return true;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            Database::query(
                sprintf('DELETE FROM %s WHERE id = ?', $this->table),
                null,
                [$id],
                'delete'
            );
        } catch (\Throwable $e) {
            Log::error('Session destroy failed', ['error' => $e->getMessage()]);
        }

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $threshold = date('Y-m-d H:i:s', time() - $this->lifetimeSeconds);

            Database::query(
                sprintf('DELETE FROM %s WHERE last_activity < ?', $this->table),
                null,
                [$threshold],
                'delete'
            );
        } catch (\Throwable $e) {
            Log::error('Session GC failed', ['error' => $e->getMessage()]);
        }

        return 0;
    }

    private function ensureTable(): void
    {
        if (self::$tableEnsured) {
            return;
        }

        try {
            $sql = sprintf(
                'CREATE TABLE IF NOT EXISTS %s (
                    id VARCHAR(128) PRIMARY KEY,
                    payload TEXT NOT NULL,
                    last_activity DATETIME NOT NULL,
                    ip_address VARCHAR(45) NULL,
                    user_agent TEXT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )',
                $this->table
            );

            Database::query($sql);
            self::$tableEnsured = true;
        } catch (\Throwable $e) {
            Log::error('Session table ensure failed', ['error' => $e->getMessage()]);
        }
    }
}
