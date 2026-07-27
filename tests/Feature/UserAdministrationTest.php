<?php

use App\AccountStatus;
use App\Models\Person;
use App\Models\User;
use App\PermissionName;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('guests cannot access user administration', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));
});

test('authorized users can view administrators and unauthorized users receive forbidden', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole(RoleName::Administrator);

    $this->actingAs($administrator)
        ->get(route('users.index'))
        ->assertOk()
        ->assertSee('Users')
        ->assertSee('Create user');

    $this->actingAs(User::factory()->create())
        ->get(route('users.index'))
        ->assertForbidden();
});

test('an authorized administrator can create a verified user with a role and an existing person link', function () {
    Notification::fake();

    $actor = User::factory()->create();
    $actor->assignRole(RoleName::Administrator);
    $person = Person::factory()->create();
    $editorRole = Role::findByName(RoleName::Editor->value);

    Livewire::actingAs($actor)
        ->test('pages::users.create')
        ->set('form.name', '  Jordan   Mensah  ')
        ->set('form.username', 'Jordan.Mensah')
        ->set('form.email', 'JORDAN@example.com')
        ->set('form.roleIds', [$editorRole->id])
        ->set('form.personId', $person->id)
        ->call('save')
        ->assertHasNoErrors();

    $createdUser = User::query()->where('email', 'jordan@example.com')->firstOrFail();

    expect($createdUser->name)->toBe('Jordan Mensah')
        ->and($createdUser->username)->toBe('jordan.mensah')
        ->and($createdUser->email_verified_at)->not->toBeNull()
        ->and($createdUser->must_change_password)->toBeTrue()
        ->and($createdUser->account_status)->toBe(AccountStatus::Active->value)
        ->and(Hash::check('password', $createdUser->password))->toBeTrue()
        ->and($createdUser->hasRole(RoleName::Editor))->toBeTrue()
        ->and($person->refresh()->user_id)->toBe($createdUser->id);

    Notification::assertNotSentTo($createdUser, VerifyEmail::class);
});

test('a super admin can create a user', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(RoleName::SuperAdmin);
    $editorRole = Role::findByName(RoleName::Editor->value);

    Livewire::actingAs($superAdmin)
        ->test('pages::users.create')
        ->set('form.name', 'Super Admin Created')
        ->set('form.username', 'super.created')
        ->set('form.email', 'super-created@example.com')
        ->set('form.roleIds', [$editorRole->id])
        ->call('save')
        ->assertHasNoErrors();

    expect(User::query()->where('email', 'super-created@example.com')->exists())->toBeTrue();
});

test('a user without creation permission cannot create users', function () {
    $unauthorizedUser = User::factory()->create();

    $this->actingAs($unauthorizedUser)
        ->get(route('users.create'))
        ->assertForbidden();
});

test('administrators cannot assign the super admin role', function () {
    $actor = User::factory()->create();
    $actor->assignRole(RoleName::Administrator);
    $superAdminRole = Role::findByName(RoleName::SuperAdmin->value);

    Livewire::actingAs($actor)
        ->test('pages::users.create')
        ->set('form.name', 'Restricted User')
        ->set('form.username', 'restricted.user')
        ->set('form.email', 'restricted@example.com')
        ->set('form.roleIds', [$superAdminRole->id])
        ->call('save')
        ->assertForbidden();

    expect(User::query()->where('email', 'restricted@example.com')->exists())->toBeFalse();
});

test('user creation validates unique identity and profile photo type', function () {
    Storage::fake('public');

    $actor = User::factory()->create();
    $actor->assignRole(RoleName::Administrator);
    $existingUser = User::factory()->create();
    $role = Role::findByName(RoleName::Editor->value);
    $invalidPhoto = UploadedFile::fake()->create('photo.php', 10, 'application/x-php');

    Livewire::actingAs($actor)
        ->test('pages::users.create')
        ->set('form.name', 'Duplicate User')
        ->set('form.username', strtoupper($existingUser->username))
        ->set('form.email', $existingUser->email)
        ->set('form.roleIds', [$role->id])
        ->set('form.photo', $invalidPhoto)
        ->call('save')
        ->assertHasErrors([
            'form.username' => ['unique'],
            'form.email' => ['unique'],
            'form.photo',
        ]);
});

test('an authorized administrator can update identity roles photo and email verification', function () {
    Storage::fake('public');
    Storage::disk('public')->put('users/photos/old.jpg', 'old-photo');

    $actor = User::factory()->create();
    $actor->assignRole(RoleName::Administrator);
    $target = User::factory()->create(['photo' => 'users/photos/old.jpg']);
    $target->assignRole(RoleName::Editor);
    $mediaRole = Role::findByName(RoleName::MediaManager->value);
    $photo = UploadedFile::fake()->image('new-photo.jpg');

    Livewire::actingAs($actor)
        ->test('pages::users.edit', ['user' => $target])
        ->set('form.name', 'Updated Administrator')
        ->set('form.email', 'updated@example.com')
        ->set('form.roleIds', [$mediaRole->id])
        ->set('form.photo', $photo)
        ->call('save')
        ->assertHasNoErrors();

    $target->refresh();

    expect($target->name)->toBe('Updated Administrator')
        ->and($target->email)->toBe('updated@example.com')
        ->and($target->email_verified_at)->toBeNull()
        ->and($target->hasRole(RoleName::MediaManager))->toBeTrue()
        ->and($target->hasRole(RoleName::Editor))->toBeFalse()
        ->and($target->photo)->not->toBe('users/photos/old.jpg');

    Storage::disk('public')->assertMissing('users/photos/old.jpg');
    Storage::disk('public')->assertExists($target->photo);
});

test('suspension records a reason revokes sessions and prevents login', function () {
    $actor = User::factory()->create();
    $actor->assignRole(RoleName::Administrator);
    $target = User::factory()->create();
    DB::table(config('session.table'))->insert([
        'id' => 'target-session',
        'user_id' => $target->id,
        'ip_address' => '203.0.113.5',
        'user_agent' => 'Pest',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);

    Livewire::actingAs($actor)
        ->test('pages::users.show', ['user' => $target])
        ->set('suspensionReason', 'Repeated unauthorized access attempts')
        ->call('suspend')
        ->assertHasNoErrors();

    $target->refresh();

    expect($target->account_status)->toBe(AccountStatus::Suspended->value)
        ->and($target->suspended_at)->not->toBeNull()
        ->and($target->suspension_reason)->toBe('Repeated unauthorized access attempts')
        ->and(DB::table(config('session.table'))->where('user_id', $target->id)->exists())->toBeFalse();

});

test('authorized administrators can reactivate a suspended account', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo([
        PermissionName::UsersView->value,
        PermissionName::UsersRestore->value,
    ]);
    $target = User::factory()->suspended()->create();

    Livewire::actingAs($actor)
        ->test('pages::users.show', ['user' => $target])
        ->call('activate')
        ->assertHasNoErrors();

    expect($target->refresh())
        ->account_status->toBe(AccountStatus::Active->value)
        ->suspended_at->toBeNull()
        ->suspension_reason->toBeNull();
});

test('users cannot suspend their own account', function () {
    $actor = User::factory()->create();
    $actor->assignRole(RoleName::SuperAdmin);

    Livewire::actingAs($actor)
        ->test('pages::users.show', ['user' => $actor])
        ->set('suspensionReason', 'Self suspension attempt')
        ->call('suspend')
        ->assertHasErrors('user');

    expect($actor->refresh()->account_status)->toBe(AccountStatus::Active->value);
});

test('the final active super admin assignment cannot be removed', function () {
    $actor = User::factory()->create();
    $actor->assignRole(RoleName::SuperAdmin);
    $administratorRole = Role::findByName(RoleName::Administrator->value);

    Livewire::actingAs($actor)
        ->test('pages::users.edit', ['user' => $actor])
        ->set('form.roleIds', [$administratorRole->id])
        ->call('save')
        ->assertHasErrors('user');

    expect($actor->refresh()->hasRole(RoleName::SuperAdmin))->toBeTrue();
});

test('ordinary administrators cannot assign roles containing permissions they do not have', function () {
    $actor = User::factory()->create();
    $actor->assignRole(RoleName::Administrator);
    $target = User::factory()->create();
    $financeRole = Role::findByName(RoleName::FinanceOfficer->value);

    Livewire::actingAs($actor)
        ->test('pages::users.edit', ['user' => $target])
        ->set('form.roleIds', [$financeRole->id])
        ->call('save')
        ->assertHasErrors('form.roleIds');

    expect($target->refresh()->roles)->toBeEmpty();
});

test('invalid role values are rejected and users without assign role permission cannot change roles', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::UsersUpdate);
    $target = User::factory()->create();

    Livewire::actingAs($actor)
        ->test('pages::users.edit', ['user' => $target])
        ->set('form.roleIds', [999999])
        ->call('save')
        ->assertHasErrors(['form.roleIds.0' => ['exists']]);

    $editorRole = Role::findByName(RoleName::Editor);

    Livewire::actingAs($actor)
        ->test('pages::users.edit', ['user' => $target])
        ->set('form.roleIds', [$editorRole->id])
        ->call('save')
        ->assertForbidden();

    expect($target->refresh()->roles)->toBeEmpty();
});

test('deleting a user preserves and unlinks the person record', function () {
    $actor = User::factory()->create();
    $actor->assignRole(RoleName::Administrator);
    $target = User::factory()->create();
    $person = Person::factory()->for($target)->create();

    Livewire::actingAs($actor)
        ->test('pages::users.show', ['user' => $target])
        ->call('delete')
        ->assertRedirectToRoute('users.index');

    $this->assertModelMissing($target);
    $this->assertModelExists($person);
    expect($person->refresh()->user_id)->toBeNull();
});

test('user details expose security readiness without exposing secrets', function () {
    $actor = User::factory()->create();
    $actor->assignRole(RoleName::Administrator);
    $target = User::factory()->withTwoFactor()->create();

    $this->actingAs($actor)
        ->get(route('users.show', $target))
        ->assertOk()
        ->assertSee('Two-factor authentication')
        ->assertSee('Passkeys')
        ->assertDontSee($target->two_factor_secret)
        ->assertDontSee($target->two_factor_recovery_codes);
});
