<?php

use Zero\Lib\DB\Blueprint;
use Zero\Lib\DB\Migration;
use Zero\Lib\DB\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->bigInteger('id', true)->autoIncrement()->primary();
            $table->string('connection', 32);
            $table->string('queue', 64)->index();
            $table->longText('payload', false);
            $table->longText('exception', false);
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
    }
};
