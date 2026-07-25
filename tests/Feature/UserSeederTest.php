<?php

use App\Models\User;
use App\RoleName;
use Database\Seeders\DatabaseSeeder;
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

test('database seeding assigns the known development user as super admin', function () {
    $this->seed(DatabaseSeeder::class);

    $administrator = User::query()
        ->where('email', UserSeeder::ADMIN_EMAIL)
        ->firstOrFail();

    expect($administrator->hasRole(RoleName::SuperAdmin))->toBeTrue();
});

test('development user seeding is idempotent', function () {
    $this->seed(UserSeeder::class);
    $this->seed(UserSeeder::class);

    expect(User::query()->count())->toBe(10);
});
