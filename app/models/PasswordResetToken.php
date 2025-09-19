<?php

namespace App\Models;

use Zero\Lib\Model;

class PasswordResetToken extends Model
{
    protected ?string $table = 'password_reset_tokens';

    protected array $fillable = [
        'email',
        'token',
        'expires_at',
    ];

    protected bool $timestamps = false;
}
