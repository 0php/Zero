<?php

namespace App\Models;

use Zero\Lib\Model;

/**
 * User model with convenience helpers for authentication workflows.
 */
class User extends Model
{
    /**
     * Attributes that are mass assignable.
     *
     * @var string[]
     */
    protected array $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'remember_token',
    ];

    /**
     * Enable created_at/updated_at maintenance.
     */
    protected bool $timestamps = true;

    public function markEmailVerified(): bool
    {
        $this->forceFill([
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->save();
    }

    public function clearRememberToken(): bool
    {
        $this->forceFill(['remember_token' => null]);

        return $this->save();
    }

    public function isEmailVerified(): bool
    {
        $value = $this->attributes['email_verified_at'] ?? null;

        return $value !== null;
    }
    
}
