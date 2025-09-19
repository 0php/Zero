<?php

declare(strict_types=1);

namespace Zero\Lib\Session\Handlers;

use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;
use Zero\Lib\Database;

class DatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    public function __construct(
        private string $table,
        private int $lifetimeSeconds
    ) {
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
                sprintf('UPDATE %s SET last_activity = ? WHERE id = ?', $this->table),
                null,
                [$now, $id],
                'update'
            );

            return true;
        } catch (\Throwable $e) {
            error_log('Session timestamp update failed: ' . $e->getMessage());

            return false;
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
            error_log('Session read failed: ' . $e->getMessage());
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        $now = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        try {
            $updated = Database::query(
                sprintf('UPDATE %s SET payload = ?, last_activity = ?, ip_address = ?, user_agent = ? WHERE id = ?', $this->table),
                null,
                [$data, $now, $ip, $agent, $id],
                'update'
            );

            if ((int) $updated === 0) {
                Database::query(
                    sprintf('INSERT INTO %s (id, payload, last_activity, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)', $this->table),
                    null,
                    [$id, $data, $now, $ip, $agent],
                    'create'
                );
            }

            return true;
        } catch (\Throwable $e) {
            error_log('Session write failed: ' . $e->getMessage());

            return false;
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
            error_log('Session destroy failed: ' . $e->getMessage());
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
            error_log('Session GC failed: ' . $e->getMessage());
        }

        return 0;
    }
}
