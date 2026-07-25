<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'System Administrator',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'email_verified_at' => now(),
            // Local development and testing credential only.
            'password' => 'password',
            'account_status' => 'active',
        ]);

        User::factory(9)->create();
    }
}
