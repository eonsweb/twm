<?php

use App\Actions\Users\CreateAdminUser;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->createAdminUser = function (): User {
        $actor = User::factory()->create();
        $actor->assignRole(RoleName::Administrator);
        $role = Role::findByName(RoleName::Editor->value);

        return app(CreateAdminUser::class)->handle(
            $actor,
            [
                'name' => 'Temporary Password User',
                'username' => 'temporary.user',
                'email' => 'temporary@example.com',
            ],
            [$role->id],
        );
    };
});

test('an admin-created user can sign in with email and is redirected to change the temporary password', function () {
    $user = ($this->createAdminUser)();

    $this->post(route('login.store'), [
        'login' => $user->email,
        'password' => CreateAdminUser::TEMPORARY_PASSWORD,
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('password.change.required'));

    $this->assertAuthenticatedAs($user);
});

test('an admin-created user can sign in with username and is redirected to change the temporary password', function () {
    $user = ($this->createAdminUser)();

    $this->post(route('login.store'), [
        'login' => strtoupper($user->username),
        'password' => CreateAdminUser::TEMPORARY_PASSWORD,
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('password.change.required'));

    $this->assertAuthenticatedAs($user);
});

test('an admin-created user cannot access protected pages before changing the temporary password', function () {
    $user = ($this->createAdminUser)();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('password.change.required'));

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertRedirect(route('password.change.required'));
});

test('the temporary password cannot be reused as the new password', function () {
    $user = ($this->createAdminUser)();

    Livewire::actingAs($user)
        ->test('pages::auth.force-password-change')
        ->set('password', CreateAdminUser::TEMPORARY_PASSWORD)
        ->set('password_confirmation', CreateAdminUser::TEMPORARY_PASSWORD)
        ->call('updatePassword')
        ->assertHasErrors(['password' => ['not_in']]);

    expect($user->refresh()->must_change_password)->toBeTrue()
        ->and(Hash::check(CreateAdminUser::TEMPORARY_PASSWORD, $user->password))->toBeTrue();
});

test('changing the temporary password hashes it clears the requirement and permits protected access', function () {
    $user = ($this->createAdminUser)();
    $newPassword = 'a-new-secure-password';

    Livewire::actingAs($user)
        ->test('pages::auth.force-password-change')
        ->set('password', $newPassword)
        ->set('password_confirmation', $newPassword)
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertRedirectToRoute('dashboard');

    $user->refresh();

    expect($user->password)->not->toBe($newPassword)
        ->and(Hash::check($newPassword, $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeFalse();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('an admin-created user may log out while a password change is pending', function () {
    $user = ($this->createAdminUser)();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});
