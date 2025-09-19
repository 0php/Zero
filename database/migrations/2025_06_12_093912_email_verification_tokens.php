<?php

use Zero\Lib\DB\Blueprint;
use Zero\Lib\DB\Migration;
use Zero\Lib\DB\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_verification_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('token', 64);
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique('token');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_tokens');
    }
};
