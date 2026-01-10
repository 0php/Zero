<?php

declare(strict_types=1);

namespace Zero\Lib\DB;

use Closure;
use Zero\Lib\Database;

/**
 * Static facade for defining and manipulating database schemas.
 */
class Schema
{
    public static function startTransaction(): void
    {
        Database::startTransaction();
    }

    public static function commit(): void
    {
        Database::commit();
    }

    public static function rollback(): void
    {
        Database::rollback();
    }

    /**
     * Create a new table.
     */
    public static function create(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table, 'create');
        $callback($blueprint);

        foreach ($blueprint->toSql() as $sql) {
            Database::query($sql);
        }
    }

    /**
     * Modify an existing table (add/drop columns, etc.).
     */
    public static function table(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table, 'table');
        $callback($blueprint);

        foreach ($blueprint->toSql() as $sql) {
            Database::query($sql);
        }
    }

    /**
     * Drop an existing table.
     */
    public static function drop(string $table): void
    {
        Database::query(sprintf('DROP TABLE `%s`', $table));
    }

    /**
     * Drop a table if it exists.
     */
    public static function dropIfExists(string $table): void
    {
        Database::query(sprintf('DROP TABLE IF EXISTS `%s`', $table));
    }

    /**
     * Drop a column from the table.
     */

    public static function dropColumn(string $table, string $column): void
    {
        Database::query(sprintf('ALTER TABLE `%s` DROP COLUMN `%s`', $table, $column));
    }

    /**
     * Drop column if it exists.
     */
    public static function dropColumnIfExists(string $table, string $column): void
    {
        Database::query(sprintf('ALTER TABLE `%s` DROP COLUMN IF EXISTS `%s`', $table, $column));
    }
}
