<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Zero\Lib\DB\Seeder;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Zero',
                'email' => 'dev@zero.com',
                'password' => \Zero\Lib\Crypto::hash('password'),
            ]
        ];

        foreach ($users as $user) {
            User::create($user);
        }
    }
}
