<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public const ADMIN_EMAIL = 'admin@example.com';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        User::query()->firstOrCreate(['email' => self::ADMIN_EMAIL], [
            'name' => 'System Administrator',
            'username' => 'admin',
            'email_verified_at' => now(),
            // Local development and testing credential only.
            'password' => 'password',
            'account_status' => 'active',
        ]);

        $usersToCreate = max(0, 10 - User::query()->count());

        User::factory($usersToCreate)->create();
    }
}
