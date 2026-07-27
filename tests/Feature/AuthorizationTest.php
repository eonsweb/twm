<?php

use App\Models\User;
use App\PermissionName;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('super admin is authorized for every protected module permission', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(RoleName::SuperAdmin);

    foreach (PermissionName::cases() as $permission) {
        expect(Gate::forUser($superAdmin)->allows($permission->value))
            ->toBeTrue($permission->value);
    }
});

test('super admin gate override grants non-sensitive abilities', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(RoleName::SuperAdmin);
    Gate::define('system.health-check', fn (): bool => false);

    expect(Gate::forUser($superAdmin)->allows('system.health-check'))->toBeTrue();
});

test('administrator can not assign the super admin role', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole(RoleName::Administrator);
    $target = User::factory()->create();

    expect(Gate::forUser($administrator)->allows(
        'assignRoles',
        [$target, [RoleName::SuperAdmin->value]],
    ))->toBeFalse();
});

test('editor can not access role management capabilities', function () {
    $editor = User::factory()->create();
    $editor->assignRole(RoleName::Editor);

    expect($editor->cannot(PermissionName::RolesView))->toBeTrue()
        ->and($editor->cannot(PermissionName::RolesAssignPermissions))->toBeTrue();
});

test('media manager can manage media but can not manage donations', function () {
    $mediaManager = User::factory()->create();
    $mediaManager->assignRole(RoleName::MediaManager);

    expect($mediaManager->can(PermissionName::MediaUpload))->toBeTrue()
        ->and($mediaManager->can(PermissionName::MediaDelete))->toBeTrue()
        ->and($mediaManager->cannot(PermissionName::DonationsView))->toBeTrue();
});

test('finance officer can manage donations but can not publish posts', function () {
    $financeOfficer = User::factory()->create();
    $financeOfficer->assignRole(RoleName::FinanceOfficer);

    expect($financeOfficer->can(PermissionName::DonationsUpdate))->toBeTrue()
        ->and($financeOfficer->can(PermissionName::DonationsExport))->toBeTrue()
        ->and($financeOfficer->cannot(PermissionName::PostsPublish))->toBeTrue();
});

test('hidden dashboard navigation is also protected by server middleware', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertDontSee('Dashboard');

    $this->get(route('dashboard'))->assertForbidden();
});

test('super admin gate bypass applies to all authorization abilities', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::SuperAdmin);

    expect(Gate::forUser($user)->allows('suspend', $user))->toBeTrue()
        ->and(Gate::forUser($user)->allows('delete', $user))->toBeTrue();
});

test('non super admin can not modify a super admin', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole(RoleName::Administrator);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(RoleName::SuperAdmin);

    expect(Gate::forUser($administrator)->denies('update', $superAdmin))->toBeTrue()
        ->and(Gate::forUser($administrator)->denies('suspend', $superAdmin))->toBeTrue()
        ->and(Gate::forUser($administrator)->denies('delete', $superAdmin))->toBeTrue()
        ->and(Gate::forUser($administrator)->denies(
            'assignRoles',
            [$superAdmin, [RoleName::Editor->value]],
        ))->toBeTrue();
});

test('suspended users can not access the admin dashboard', function () {
    $user = User::factory()->suspended()->create();
    $user->assignRole(RoleName::SuperAdmin);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('authorization seeder is idempotent', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::query()->count())->toBe(count(RoleName::cases()))
        ->and(Permission::query()->count())->toBe(count(PermissionName::cases()));
});
