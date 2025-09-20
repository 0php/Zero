<?php

namespace Database\Seeders;

use Zero\Lib\DB\Seeder;
use App\Models\Role as RoleModel;

class Role extends Seeder
{
    public function run(): void
    {
        // admin and user
        $roles = [
            'admin',
            'user',
        ];

        foreach ($roles as $role) {
            RoleModel::create([
                'name' => $role,
                'description' => $role
            ]);
        }
    }
}
