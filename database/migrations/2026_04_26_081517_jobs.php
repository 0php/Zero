<?php

use Zero\Lib\DB\Blueprint;
use Zero\Lib\DB\Migration;
use Zero\Lib\DB\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigInteger('id', true)->autoIncrement()->primary();
            $table->string('queue', 64)->index();
            $table->longText('payload', false);
            $table->integer('attempts', true)->default(0);
            $table->timestamp('reserved_at', true)->nullable()->index();
            $table->timestamp('available_at')->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
