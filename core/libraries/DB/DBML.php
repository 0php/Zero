<?php

declare(strict_types=1);

namespace Zero\Lib\DB;

use Zero\Lib\Database;

class DBML extends QueryBuilder
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
}
