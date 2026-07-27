<?php

use App\PermissionName;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('roles and permissions are seeded with machine friendly names', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::query()->pluck('name')->sort()->values()->all())
        ->toBe(collect(RoleName::values())->sort()->values()->all())
        ->and(Permission::query()->pluck('name')->sort()->values()->all())
        ->toBe(collect(PermissionName::values())->sort()->values()->all());
});

test('authorization seeding is idempotent', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::query()->count())->toBe(count(RoleName::cases()))
        ->and(Permission::query()->count())->toBe(count(PermissionName::cases()));
});

test('default role permissions are synchronized predictably', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = Role::findByName(RoleName::SuperAdmin);
    $administrator = Role::findByName(RoleName::Administrator);
    $editor = Role::findByName(RoleName::Editor);
    $mediaManager = Role::findByName(RoleName::MediaManager);
    $financeOfficer = Role::findByName(RoleName::FinanceOfficer);

    expect($superAdmin->permissions()->count())->toBe(count(PermissionName::cases()))
        ->and($administrator->hasPermissionTo(PermissionName::RolesAssignPermissions))->toBeTrue()
        ->and($administrator->hasPermissionTo(PermissionName::SettingsUpdate))->toBeTrue()
        ->and($administrator->hasPermissionTo(PermissionName::DonationsView))->toBeFalse()
        ->and($editor->hasPermissionTo(PermissionName::WebsiteContentPublish))->toBeTrue()
        ->and($editor->hasPermissionTo(PermissionName::UsersView))->toBeFalse()
        ->and($mediaManager->hasPermissionTo(PermissionName::MediaDelete))->toBeTrue()
        ->and($mediaManager->hasPermissionTo(PermissionName::DonationsView))->toBeFalse()
        ->and($financeOfficer->hasPermissionTo(PermissionName::DonationsExport))->toBeTrue()
        ->and($financeOfficer->hasPermissionTo(PermissionName::PostsPublish))->toBeFalse();
});

test('legacy display role names are migrated without creating duplicates', function () {
    Role::query()->create(['name' => 'Super Admin', 'guard_name' => 'web']);
    Permission::query()->create(['name' => 'roles.manage-permissions', 'guard_name' => 'web']);

    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::query()->where('name', 'Super Admin')->exists())->toBeFalse()
        ->and(Role::query()->where('name', RoleName::SuperAdmin->value)->count())->toBe(1)
        ->and(Permission::query()->where('name', 'roles.manage-permissions')->exists())->toBeFalse()
        ->and(Permission::query()->where('name', PermissionName::RolesAssignPermissions->value)->count())->toBe(1);
});
