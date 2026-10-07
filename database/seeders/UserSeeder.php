<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public const ADMIN_EMAIL = 'admin@example.com';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Local / Testing
        |--------------------------------------------------------------------------
        |
        | Use simple development credentials locally.
        |
        */
        if (app()->environment(['local', 'testing'])) {
            User::query()->firstOrCreate(
                [
                    'email' => self::ADMIN_EMAIL,
                ],
                [
                    'name' => 'System Administrator',
                    'username' => 'admin',
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                    'account_status' => 'active',
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Production
        |--------------------------------------------------------------------------
        |
        | Production credentials are taken from the server .env file.
        | Do not store the real production password in GitHub.
        |
        */
        if (app()->environment('production')) {
            $email = env('INITIAL_ADMIN_EMAIL');
            $password = env('INITIAL_ADMIN_PASSWORD');

            if (! $email || ! $password) {
                $this->command?->warn(
                    'Initial admin was not created because INITIAL_ADMIN_EMAIL or INITIAL_ADMIN_PASSWORD is missing.'
                );

                return;
            }

            User::query()->firstOrCreate(
                [
                    'email' => $email,
                ],
                [
                    'name' => env('INITIAL_ADMIN_NAME', 'Administrator'),
                    'username' => env('INITIAL_ADMIN_USERNAME', 'admin'),
                    'email_verified_at' => now(),
                    'password' => Hash::make($password),
                    'account_status' => 'active',
                ]
            );
        }
    }
}
