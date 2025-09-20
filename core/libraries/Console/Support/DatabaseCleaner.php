<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Support;

use RuntimeException;
use Zero\Lib\Database;

final class DatabaseCleaner
{
    /**
     * Drop all tables for the active database connection.
     *
     * @return array<int, string> Dropped table names.
     */
    public static function dropAllTables(): array
    {
        $connection = config('database.connection');
        $config = config('database');
        $connectionConfig = is_array($config) ? ($config[$connection] ?? null) : null;
        $driver = is_array($connectionConfig) ? ($connectionConfig['driver'] ?? null) : null;

        return match ($driver) {
            'mysql' => self::dropMysqlTables(),
            'sqlite3' => self::dropSqliteTables(),
            'pgsql' => self::dropPostgresTables(),
            default => throw new RuntimeException('Unsupported database driver for migrate:fresh command'),
        };
    }

    /**
     * @return array<int, string>
     */
    private static function dropMysqlTables(): array
    {
        $tables = Database::fetch('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
        if (empty($tables)) {
            return [];
        }

        Database::query('SET FOREIGN_KEY_CHECKS=0');

        $dropped = [];
        foreach ($tables as $row) {
            $table = array_values($row)[0] ?? null;
            if (!$table) {
                continue;
            }

            Database::query(sprintf('DROP TABLE IF EXISTS `%s`', $table));
            $dropped[] = (string) $table;
        }

        Database::query('SET FOREIGN_KEY_CHECKS=1');

        return $dropped;
    }

    /**
     * @return array<int, string>
     */
    private static function dropSqliteTables(): array
    {
        $tables = Database::fetch("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");
        if (empty($tables)) {
            return [];
        }

        Database::query('PRAGMA foreign_keys = OFF');

        $dropped = [];
        foreach ($tables as $row) {
            $table = $row['name'] ?? array_values($row)[0] ?? null;
            if (!$table) {
                continue;
            }

            Database::query(sprintf('DROP TABLE IF EXISTS "%s"', $table));
            $dropped[] = (string) $table;
        }

        Database::query('PRAGMA foreign_keys = ON');

        return $dropped;
    }

    /**
     * @return array<int, string>
     */
    private static function dropPostgresTables(): array
    {
        $tables = Database::fetch("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
        if (empty($tables)) {
            return [];
        }

        $dropped = [];
        foreach ($tables as $row) {
            $table = $row['tablename'] ?? array_values($row)[0] ?? null;
            if (!$table) {
                continue;
            }

            Database::query(sprintf('DROP TABLE IF EXISTS "%s" CASCADE', $table));
            $dropped[] = (string) $table;
        }

        return $dropped;
    }
}
