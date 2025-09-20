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
    
    public function roles()
    {
        return $this->hasMany(RoleUser::class);
    }

    public function assignRole($role)
    {
        RoleUser::create([
            'role_id' => Role::query()->where('name', $role)->first()->id,
            'user_id' => $this->id
        ]);
    }

    public function hasRole($role)
    {
        return RoleUser::query()->where('role_id', Role::query()->where('name', $role)->first()->id)->where('user_id', $this->id)->exists();
    }

    public function hasManyRoles($roles)
    {
        if(is_array($roles)) {
            return RoleUser::query()->whereIn('role_id', Role::query()->whereIn('name', $roles)->select('id'))->where('user_id', $this->id)->exists();
        }
        return RoleUser::query()->where('role_id', Role::query()->where('name', $roles)->first()->id)->where('user_id', $this->id)->exists();
    }
}
