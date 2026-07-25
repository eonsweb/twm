<?php

use App\Models\User;
use Database\Seeders\UserSeeder;

test('user seeder creates exactly ten users', function () {
    $this->seed(UserSeeder::class);

    expect(User::query()->count())->toBe(10);

    $administrator = User::query()->where('username', 'admin')->firstOrFail();

    expect($administrator)
        ->name->toBe('System Administrator')
        ->email->toBe('admin@example.com')
        ->account_status->toBe('active')
        ->email_verified_at->not->toBeNull();
});

test('seeded usernames and emails are unique', function () {
    $this->seed(UserSeeder::class);

    $users = User::query()->get(['username', 'email']);

    expect($users->pluck('username')->unique()->count())->toBe(10)
        ->and($users->pluck('email')->unique()->count())->toBe(10);
});
