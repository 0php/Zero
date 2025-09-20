<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User as UserModel;
use Zero\Lib\DB\Seeder;

class User extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Zero',
                'email' => 'dev@zero.com',
                'password' => \Zero\Lib\Crypto::hash('password'),
                'email_verified_at' => date('Y-m-d H:i:s'),
            ]
        ];

        foreach ($users as $user) {
            $user = UserModel::create($user);

            $user->assignRole('admin');
        }
    }
}
