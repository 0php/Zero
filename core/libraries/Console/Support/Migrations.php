<?php

declare(strict_types=1);

namespace Zero\Lib\Console\Support;

use Zero\Lib\DB\MigrationRepository;
use Zero\Lib\DB\Migrator;

final class Migrations
{
    public static function makeMigrator(): Migrator
    {
        $repository = new MigrationRepository();

        return new Migrator($repository, base('database/migrations'));
    }
}
