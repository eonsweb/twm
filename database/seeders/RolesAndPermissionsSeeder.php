<?php

namespace Database\Seeders;

use App\Models\User;
use App\PermissionName;
use App\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    private const GUARD = 'web';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::cases() as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission->value,
                'guard_name' => self::GUARD,
            ]);
        }

        foreach ($this->rolePermissions() as $roleName => $permissions) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => self::GUARD,
            ]);

            $role->syncPermissions($permissions);
        }

        $this->assignDevelopmentSuperAdmin();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Get the permissions assigned to each system role.
     *
     * @return array<string, list<string>>
     */
    private function rolePermissions(): array
    {
        return [
            RoleName::SuperAdmin->value => PermissionName::values(),
            RoleName::Administrator->value => $this->permissionValues([
                PermissionName::DashboardView,
                PermissionName::UsersView,
                PermissionName::UsersCreate,
                PermissionName::UsersUpdate,
                PermissionName::UsersDelete,
                PermissionName::UsersSuspend,
                PermissionName::UsersRestore,
                PermissionName::UsersAssignRoles,
                PermissionName::SermonsView,
                PermissionName::SermonsCreate,
                PermissionName::SermonsUpdate,
                PermissionName::SermonsDelete,
                PermissionName::SermonsPublish,
                PermissionName::EventsView,
                PermissionName::EventsCreate,
                PermissionName::EventsUpdate,
                PermissionName::EventsDelete,
                PermissionName::EventsPublish,
                PermissionName::MinistriesView,
                PermissionName::MinistriesCreate,
                PermissionName::MinistriesUpdate,
                PermissionName::MinistriesDelete,
                PermissionName::PostsView,
                PermissionName::PostsCreate,
                PermissionName::PostsUpdate,
                PermissionName::PostsDelete,
                PermissionName::PostsPublish,
                PermissionName::MediaView,
                PermissionName::MediaUpload,
                PermissionName::MediaUpdate,
                PermissionName::MediaDelete,
                PermissionName::PagesView,
                PermissionName::PagesCreate,
                PermissionName::PagesUpdate,
                PermissionName::PagesDelete,
                PermissionName::PagesPublish,
                PermissionName::PrayerRequestsView,
                PermissionName::PrayerRequestsUpdate,
                PermissionName::PrayerRequestsDelete,
                PermissionName::ContactSubmissionsView,
                PermissionName::ContactSubmissionsUpdate,
                PermissionName::ContactSubmissionsDelete,
                PermissionName::SettingsView,
                PermissionName::SettingsUpdate,
                PermissionName::ActivityLogsView,
            ]),
            RoleName::Editor->value => $this->permissionValues([
                PermissionName::DashboardView,
                PermissionName::SermonsView,
                PermissionName::SermonsCreate,
                PermissionName::SermonsUpdate,
                PermissionName::SermonsDelete,
                PermissionName::SermonsPublish,
                PermissionName::EventsView,
                PermissionName::EventsCreate,
                PermissionName::EventsUpdate,
                PermissionName::EventsDelete,
                PermissionName::EventsPublish,
                PermissionName::MinistriesView,
                PermissionName::MinistriesCreate,
                PermissionName::MinistriesUpdate,
                PermissionName::MinistriesDelete,
                PermissionName::PostsView,
                PermissionName::PostsCreate,
                PermissionName::PostsUpdate,
                PermissionName::PostsDelete,
                PermissionName::PostsPublish,
                PermissionName::PagesView,
                PermissionName::PagesCreate,
                PermissionName::PagesUpdate,
                PermissionName::PagesDelete,
                PermissionName::PagesPublish,
                PermissionName::MediaView,
                PermissionName::MediaUpload,
                PermissionName::MediaUpdate,
                PermissionName::MediaDelete,
                PermissionName::PrayerRequestsView,
                PermissionName::PrayerRequestsUpdate,
                PermissionName::PrayerRequestsDelete,
                PermissionName::ContactSubmissionsView,
                PermissionName::ContactSubmissionsUpdate,
                PermissionName::ContactSubmissionsDelete,
            ]),
            RoleName::MediaManager->value => $this->permissionValues([
                PermissionName::DashboardView,
                PermissionName::SermonsView,
                PermissionName::MediaView,
                PermissionName::MediaUpload,
                PermissionName::MediaUpdate,
                PermissionName::MediaDelete,
            ]),
            RoleName::FinanceOfficer->value => $this->permissionValues([
                PermissionName::DashboardView,
                PermissionName::DonationsView,
                PermissionName::DonationsCreate,
                PermissionName::DonationsUpdate,
                PermissionName::DonationsDelete,
                PermissionName::DonationsExport,
            ]),
        ];
    }

    /**
     * Convert permission enums to their stored names.
     *
     * @param  list<PermissionName>  $permissions
     * @return list<string>
     */
    private function permissionValues(array $permissions): array
    {
        return array_map(
            static fn (PermissionName $permission): string => $permission->value,
            $permissions,
        );
    }

    /**
     * Assign the known development administrator without affecting production users.
     */
    private function assignDevelopmentSuperAdmin(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        User::query()
            ->where('email', UserSeeder::ADMIN_EMAIL)
            ->first()
            ?->syncRoles([RoleName::SuperAdmin]);
    }
}
