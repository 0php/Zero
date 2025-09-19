<?php

declare(strict_types=1);

namespace Zero\Lib\DB;

abstract class Seeder
{
    abstract public function run(): void;
}
