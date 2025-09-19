<?php

declare(strict_types=1);

namespace Zero\Lib\DB;

abstract class Migration
{
    /**
     * Run the migrations.
     */
    abstract public function up(): void;

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optional
    }
}
