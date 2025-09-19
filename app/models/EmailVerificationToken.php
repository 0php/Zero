<?php

namespace App\Models;

use Zero\Lib\Model;

class EmailVerificationToken extends Model
{
    protected ?string $table = 'email_verification_tokens';

    protected array $fillable = [
        'user_id',
        'token',
        'expires_at',
    ];

    protected bool $timestamps = false;
}
