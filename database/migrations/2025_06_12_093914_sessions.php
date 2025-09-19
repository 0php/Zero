<?php

use Zero\Lib\DB\Blueprint;
use Zero\Lib\DB\Migration;
use Zero\Lib\DB\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id', 128);
            $table->primary('id');
            $table->bigInteger('user_id', false, true);
            $table->string('ip_address', 45, true);
            $table->text('user_agent');
            $table->text('payload', false);
            $table->timestamp('last_activity', false, 'CURRENT_TIMESTAMP');
            $table->index('last_activity');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
