<?php

namespace App\Models;

use Zero\Lib\Model;

class RoleUser extends Model
{
    /**
     * @var string[]
     */
    protected array $fillable = [
        'role_id',
        'user_id',
    ];
}
