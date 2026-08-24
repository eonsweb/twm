<?php

namespace Database\Seeders;

use App\Models\User;
use App\PermissionName;
use App\RoleName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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

        DB::transaction(function (): void {
            $this->migrateLegacyNames();

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

                $role->givePermissionTo($permissions);
            }
        });

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
                PermissionName::RolesView,
                PermissionName::RolesCreate,
                PermissionName::RolesUpdate,
                PermissionName::RolesDelete,
                PermissionName::RolesAssignPermissions,
                PermissionName::SermonsView,
                PermissionName::SermonsCreate,
                PermissionName::SermonsUpdate,
                PermissionName::SermonsDelete,
                PermissionName::SermonsPublish,
                PermissionName::SermonsUnpublish,
                PermissionName::SermonsSchedule,
                PermissionName::SermonsArchive,
                PermissionName::SermonsRestore,
                PermissionName::SermonsForceDelete,
                PermissionName::SermonsFeature,
                PermissionName::SpeakersManage,
                PermissionName::EventsView,
                PermissionName::EventsCreate,
                PermissionName::EventsUpdate,
                PermissionName::EventsDelete,
                PermissionName::EventsRestore,
                PermissionName::EventsForceDelete,
                PermissionName::EventsPublish,
                PermissionName::EventsCancel,
                PermissionName::EventTypesView,
                PermissionName::EventTypesCreate,
                PermissionName::EventTypesUpdate,
                PermissionName::EventTypesDelete,
                PermissionName::ServiceSchedulesView,
                PermissionName::ServiceSchedulesCreate,
                PermissionName::ServiceSchedulesUpdate,
                PermissionName::ServiceSchedulesDelete,
                PermissionName::MinistriesView,
                PermissionName::MinistriesCreate,
                PermissionName::MinistriesUpdate,
                PermissionName::MinistriesDelete,
                PermissionName::MinistriesRestore,
                PermissionName::MinistriesForceDelete,
                PermissionName::MinistriesPublish,
                PermissionName::BooksView,
                PermissionName::BooksCreate,
                PermissionName::BooksUpdate,
                PermissionName::BooksPublish,
                PermissionName::BooksArchive,
                PermissionName::BooksRestore,
                PermissionName::BooksDelete,
                PermissionName::LeadershipView,
                PermissionName::LeadershipCreate,
                PermissionName::LeadershipUpdate,
                PermissionName::LeadershipDelete,
                PermissionName::LeadershipPublish,
                PermissionName::LeadershipReorder,
                PermissionName::PostsView,
                PermissionName::PostsCreate,
                PermissionName::PostsUpdate,
                PermissionName::PostsDelete,
                PermissionName::PostsPublish,
                PermissionName::PostsRestore,
                PermissionName::PostsForceDelete,
                PermissionName::PostsArchive,
                PermissionName::PostsPreview,
                PermissionName::PostsManageAuthors,
                PermissionName::PostCategoriesManage,
                PermissionName::PostTagsManage,
                PermissionName::MediaView,
                PermissionName::MediaCreate,
                PermissionName::MediaUpload,
                PermissionName::MediaUpdate,
                PermissionName::MediaDelete,
                PermissionName::MediaRestore,
                PermissionName::MediaForceDelete,
                PermissionName::MediaDownload,
                PermissionName::MediaManageFolders,
                PermissionName::MediaManagePrivate,
                PermissionName::WebsiteContentView,
                PermissionName::WebsiteContentUpdate,
                PermissionName::WebsiteContentPublish,
                PermissionName::PagesView,
                PermissionName::PagesCreate,
                PermissionName::PagesUpdate,
                PermissionName::PagesDelete,
                PermissionName::PagesPublish,
                PermissionName::PagesRestore,
                PermissionName::PagesForceDelete,
                PermissionName::PagesManageSections,
                PermissionName::PagesManageSeo,
                PermissionName::PagesPreview,
                PermissionName::DonationsView,
                PermissionName::DonationsCreate,
                PermissionName::DonationsUpdate,
                PermissionName::DonationsDelete,
                PermissionName::DonationsRestore,
                PermissionName::DonationsApprove,
                PermissionName::DonationsRefund,
                PermissionName::DonationsExport,
                PermissionName::DonationsPrintReceipt,
                PermissionName::DonorsView,
                PermissionName::DonorsCreate,
                PermissionName::DonorsUpdate,
                PermissionName::DonorsDelete,
                PermissionName::DonationCategoriesManage,
                PermissionName::DonationCampaignsManage,
                PermissionName::PaymentTransactionsView,
                PermissionName::PrayerRequestsView,
                PermissionName::PrayerRequestsCreate,
                PermissionName::PrayerRequestsUpdate,
                PermissionName::PrayerRequestsAssign,
                PermissionName::PrayerRequestsAddNotes,
                PermissionName::PrayerRequestsViewContactDetails,
                PermissionName::PrayerRequestsViewSensitiveMetadata,
                PermissionName::PrayerRequestsMarkAnswered,
                PermissionName::PrayerRequestsPublish,
                PermissionName::PrayerRequestsArchive,
                PermissionName::PrayerRequestsDelete,
                PermissionName::PrayerRequestsRestore,
                PermissionName::PrayerRequestsForceDelete,
                PermissionName::ContactSubmissionsView,
                PermissionName::ContactSubmissionsCreate,
                PermissionName::ContactSubmissionsUpdate,
                PermissionName::ContactSubmissionsAssign,
                PermissionName::ContactSubmissionsReply,
                PermissionName::ContactSubmissionsResolve,
                PermissionName::ContactSubmissionsManageNotes,
                PermissionName::ContactSubmissionsMarkSpam,
                PermissionName::ContactSubmissionsDelete,
                PermissionName::ContactSubmissionsRestore,
                PermissionName::ContactSubmissionsForceDelete,
                PermissionName::SettingsView,
                PermissionName::SettingsUpdate,
                PermissionName::SettingsGeneralUpdate,
                PermissionName::SettingsChurchUpdate,
                PermissionName::SettingsContactUpdate,
                PermissionName::SettingsServiceTimesUpdate,
                PermissionName::SettingsBrandingUpdate,
                PermissionName::SettingsSocialUpdate,
                PermissionName::SettingsEmailUpdate,
                PermissionName::SettingsDonationsUpdate,
                PermissionName::SettingsLocalizationUpdate,
                PermissionName::ActivityLogsView,
                PermissionName::ActivityLogsViewDetails,
                PermissionName::ActivityLogsExport,
            ]),
            RoleName::Editor->value => $this->permissionValues([
                PermissionName::DashboardView,
                PermissionName::SermonsView,
                PermissionName::SermonsCreate,
                PermissionName::SermonsUpdate,
                PermissionName::SermonsDelete,
                PermissionName::EventsView,
                PermissionName::EventsCreate,
                PermissionName::EventsUpdate,
                PermissionName::EventsDelete,
                PermissionName::EventsPublish,
                PermissionName::EventsCancel,
                PermissionName::EventTypesView,
                PermissionName::ServiceSchedulesView,
                PermissionName::ServiceSchedulesCreate,
                PermissionName::ServiceSchedulesUpdate,
                PermissionName::ServiceSchedulesDelete,
                PermissionName::MinistriesView,
                PermissionName::MinistriesCreate,
                PermissionName::MinistriesUpdate,
                PermissionName::MinistriesDelete,
                PermissionName::MinistriesRestore,
                PermissionName::MinistriesPublish,
                PermissionName::BooksView,
                PermissionName::BooksCreate,
                PermissionName::BooksUpdate,
                PermissionName::BooksPublish,
                PermissionName::BooksArchive,
                PermissionName::LeadershipView,
                PermissionName::LeadershipCreate,
                PermissionName::LeadershipUpdate,
                PermissionName::LeadershipDelete,
                PermissionName::LeadershipPublish,
                PermissionName::LeadershipReorder,
                PermissionName::PostsView,
                PermissionName::PostsCreate,
                PermissionName::PostsUpdate,
                PermissionName::PostsDelete,
                PermissionName::PostsPublish,
                PermissionName::PostsRestore,
                PermissionName::PostsArchive,
                PermissionName::PostsPreview,
                PermissionName::PostCategoriesManage,
                PermissionName::PostTagsManage,
                PermissionName::PagesView,
                PermissionName::PagesCreate,
                PermissionName::PagesUpdate,
                PermissionName::PagesDelete,
                PermissionName::PagesPublish,
                PermissionName::PagesRestore,
                PermissionName::PagesManageSections,
                PermissionName::PagesManageSeo,
                PermissionName::PagesPreview,
                PermissionName::MediaView,
                PermissionName::MediaCreate,
                PermissionName::MediaUpload,
                PermissionName::MediaUpdate,
                PermissionName::MediaDelete,
                PermissionName::MediaRestore,
                PermissionName::MediaDownload,
                PermissionName::MediaManageFolders,
                PermissionName::WebsiteContentView,
                PermissionName::WebsiteContentUpdate,
                PermissionName::WebsiteContentPublish,
                PermissionName::PrayerRequestsView,
                PermissionName::PrayerRequestsCreate,
                PermissionName::PrayerRequestsUpdate,
                PermissionName::PrayerRequestsAssign,
                PermissionName::PrayerRequestsAddNotes,
                PermissionName::PrayerRequestsViewContactDetails,
                PermissionName::PrayerRequestsMarkAnswered,
                PermissionName::PrayerRequestsArchive,
                PermissionName::PrayerRequestsDelete,
                PermissionName::PrayerRequestsRestore,
                PermissionName::ContactSubmissionsView,
                PermissionName::ContactSubmissionsUpdate,
                PermissionName::ContactSubmissionsAssign,
                PermissionName::ContactSubmissionsReply,
                PermissionName::ContactSubmissionsResolve,
                PermissionName::ContactSubmissionsManageNotes,
                PermissionName::ContactSubmissionsMarkSpam,
                PermissionName::SettingsView,
                PermissionName::SettingsChurchUpdate,
                PermissionName::SettingsContactUpdate,
                PermissionName::SettingsServiceTimesUpdate,
                PermissionName::SettingsSocialUpdate,
            ]),
            RoleName::MediaManager->value => $this->permissionValues([
                PermissionName::DashboardView,
                PermissionName::SermonsView,
                PermissionName::SermonsUpdate,
                PermissionName::BooksView,
                PermissionName::MediaView,
                PermissionName::MediaCreate,
                PermissionName::MediaUpload,
                PermissionName::MediaUpdate,
                PermissionName::MediaDelete,
                PermissionName::MediaRestore,
                PermissionName::MediaDownload,
                PermissionName::MediaManageFolders,
                PermissionName::MediaManagePrivate,
                PermissionName::WebsiteContentView,
                PermissionName::SettingsView,
                PermissionName::SettingsBrandingUpdate,
                PermissionName::SettingsSocialUpdate,
            ]),
            RoleName::FinanceOfficer->value => $this->permissionValues([
                PermissionName::DashboardView,
                PermissionName::DonationsView,
                PermissionName::DonationsCreate,
                PermissionName::DonationsUpdate,
                PermissionName::DonationsDelete,
                PermissionName::DonationsRestore,
                PermissionName::DonationsApprove,
                PermissionName::DonationsRefund,
                PermissionName::DonationsExport,
                PermissionName::DonationsPrintReceipt,
                PermissionName::DonorsView,
                PermissionName::DonorsCreate,
                PermissionName::DonorsUpdate,
                PermissionName::DonorsDelete,
                PermissionName::DonationCategoriesManage,
                PermissionName::DonationCampaignsManage,
                PermissionName::PaymentTransactionsView,
                PermissionName::SettingsView,
                PermissionName::SettingsDonationsUpdate,
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

    private function migrateLegacyNames(): void
    {
        foreach (RoleName::cases() as $roleName) {
            $legacyRole = Role::query()
                ->where('name', $roleName->label())
                ->where('guard_name', self::GUARD)
                ->first();

            if ($legacyRole === null) {
                continue;
            }

            $currentRole = Role::query()
                ->where('name', $roleName->value)
                ->where('guard_name', self::GUARD)
                ->first();

            if ($currentRole === null) {
                $legacyRole->update(['name' => $roleName->value]);

                continue;
            }

            $legacyUsers = User::query()
                ->whereHas(
                    'roles',
                    fn ($query) => $query->whereKey($legacyRole->getKey()),
                )
                ->get();

            foreach ($legacyUsers as $user) {
                $user->assignRole($currentRole);
                $user->removeRole($legacyRole);
            }

            $legacyRole->delete();
        }

        $legacyPermission = Permission::query()
            ->where('name', 'roles.manage-permissions')
            ->where('guard_name', self::GUARD)
            ->first();

        if ($legacyPermission === null) {
            return;
        }

        $currentPermission = Permission::query()
            ->where('name', PermissionName::RolesAssignPermissions->value)
            ->where('guard_name', self::GUARD)
            ->first();

        if ($currentPermission === null) {
            $legacyPermission->update(['name' => PermissionName::RolesAssignPermissions->value]);

            return;
        }

        $legacyRoles = Role::query()
            ->whereHas(
                'permissions',
                fn ($query) => $query->whereKey($legacyPermission->getKey()),
            )
            ->get();

        foreach ($legacyRoles as $role) {
            $role->givePermissionTo($currentPermission);
        }

        $legacyPermission->delete();
    }
}
