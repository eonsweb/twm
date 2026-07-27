<?php

use App\Models\User;
use App\PermissionName;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('guests are redirected from every role management page', function () {
    $role = Role::findByName(RoleName::Editor);

    $this->get(route('admin.roles.index'))->assertRedirect(route('login'));
    $this->get(route('admin.roles.create'))->assertRedirect(route('login'));
    $this->get(route('admin.roles.edit', $role))->assertRedirect(route('login'));
});

test('users without role permissions receive forbidden responses', function () {
    $user = User::factory()->create();
    $role = Role::findByName(RoleName::Editor);

    $this->actingAs($user)->get(route('admin.roles.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.roles.create'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.roles.edit', $role))->assertForbidden();
});

test('authorized users can view and search roles', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::RolesView);
    Role::query()->create(['name' => 'service-coordinator', 'guard_name' => 'web']);

    $this->actingAs($actor)
        ->get(route('admin.roles.index'))
        ->assertOk()
        ->assertSee('Roles &amp; Permissions', false)
        ->assertSee('Service Coordinator');

    Livewire::actingAs($actor)
        ->test('pages::roles.index')
        ->set('search', 'service')
        ->assertSee('Service Coordinator')
        ->assertDontSee('Finance Officer');
});

test('authorized users can create normalized roles and synchronize permissions', function () {
    $actor = User::factory()->create();
    $actor->assignRole(RoleName::SuperAdmin);

    Livewire::actingAs($actor)
        ->test('pages::roles.create')
        ->set('form.name', '  Service Coordinator  ')
        ->set('form.permissionNames', [
            PermissionName::DashboardView->value,
            PermissionName::EventsView->value,
        ])
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show');

    $role = Role::findByName('service-coordinator');

    expect($role->hasAllPermissions([
        PermissionName::DashboardView,
        PermissionName::EventsView,
    ]))->toBeTrue();
});

test('role creation validates required unique and invalid permission values', function () {
    $actor = User::factory()->create();
    $actor->assignRole(RoleName::SuperAdmin);

    Livewire::actingAs($actor)
        ->test('pages::roles.create')
        ->set('form.name', '')
        ->call('save')
        ->assertHasErrors(['form.name' => ['required']]);

    Livewire::actingAs($actor)
        ->test('pages::roles.create')
        ->set('form.name', RoleName::Editor->label())
        ->set('form.permissionNames', ['not-a-real-permission'])
        ->call('save')
        ->assertHasErrors(['form.name' => ['unique'], 'form.permissionNames.0' => ['exists']]);
});

test('authorized users can update a role and its permissions', function () {
    $actor = User::factory()->create();
    $actor->assignRole(RoleName::SuperAdmin);
    $role = Role::query()->create(['name' => 'volunteer', 'guard_name' => 'web']);
    $role->givePermissionTo(PermissionName::DashboardView);

    Livewire::actingAs($actor)
        ->test('pages::roles.edit', ['role' => $role])
        ->set('form.name', 'Team Volunteer')
        ->set('form.permissionNames', [
            PermissionName::DashboardView->value,
            PermissionName::EventsView->value,
        ])
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show');

    $role->refresh();

    expect($role->name)->toBe('team-volunteer')
        ->and($role->hasPermissionTo(PermissionName::EventsView))->toBeTrue();
});

test('users cannot invoke hidden permission actions without authorization', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::RolesCreate);

    Livewire::actingAs($actor)
        ->test('pages::roles.create')
        ->call('selectAllPermissions')
        ->assertForbidden();
});

test('users without delete permission cannot invoke the role deletion action', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::RolesView);
    $role = Role::query()->create(['name' => 'protected-by-authorization', 'guard_name' => 'web']);

    Livewire::actingAs($actor)
        ->test('pages::roles.index')
        ->set('deletingRoleId', $role->id)
        ->call('delete')
        ->assertForbidden();

    $this->assertModelExists($role);
});

test('ordinary administrators cannot grant permissions they do not have', function () {
    $administrator = User::factory()->create();
    $administrator->assignRole(RoleName::Administrator);

    Livewire::actingAs($administrator)
        ->test('pages::roles.create')
        ->set('form.name', 'finance-helper')
        ->set('form.permissionNames', [PermissionName::DonationsExport->value])
        ->call('save')
        ->assertHasErrors('form.permissionNames');

    expect(Role::query()->where('name', 'finance-helper')->exists())->toBeFalse();
});

test('an authorized user can delete an unassigned custom role after confirmation', function () {
    $actor = User::factory()->create();
    $actor->assignRole(RoleName::SuperAdmin);
    $role = Role::query()->create(['name' => 'temporary-role', 'guard_name' => 'web']);

    Livewire::actingAs($actor)
        ->test('pages::roles.index')
        ->call('confirmDelete', $role->id)
        ->assertSet('showDeleteModal', true)
        ->call('delete')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show');

    $this->assertModelMissing($role);
});

test('protected and assigned roles cannot be deleted through hidden actions', function () {
    $actor = User::factory()->create();
    $actor->assignRole(RoleName::SuperAdmin);
    $protectedRole = Role::findByName(RoleName::SuperAdmin);
    $assignedRole = Role::query()->create(['name' => 'assigned-role', 'guard_name' => 'web']);
    User::factory()->create()->assignRole($assignedRole);

    Livewire::actingAs($actor)
        ->test('pages::roles.index')
        ->set('deletingRoleId', $protectedRole->id)
        ->call('delete')
        ->assertHasErrors('deleteRole');

    Livewire::actingAs($actor)
        ->test('pages::roles.index')
        ->set('deletingRoleId', $assignedRole->id)
        ->call('delete')
        ->assertHasErrors('deleteRole');

    $this->assertModelExists($protectedRole);
    $this->assertModelExists($assignedRole);
});

test('the super admin role cannot be modified through a hidden action', function () {
    $actor = User::factory()->create();
    $actor->assignRole(RoleName::SuperAdmin);
    $role = Role::findByName(RoleName::SuperAdmin);

    Livewire::actingAs($actor)
        ->test('pages::roles.edit', ['role' => $role])
        ->set('form.permissionNames', [PermissionName::DashboardView->value])
        ->call('save')
        ->assertHasErrors('form.name');

    expect($role->refresh()->permissions()->count())->toBe(Permission::query()->count());
});
