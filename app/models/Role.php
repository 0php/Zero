<?php

namespace App\Models;

use Zero\Lib\Model;

class Role extends Model
{
    /**
     * @var string[]
     */
    protected array $fillable = [
        'name',
        'description',
    ];
}
