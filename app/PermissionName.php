<?php

namespace App;

enum PermissionName: string
{
    case DashboardView = 'dashboard.view';

    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';
    case UsersDelete = 'users.delete';
    case UsersSuspend = 'users.suspend';
    case UsersRestore = 'users.restore';
    case UsersAssignRoles = 'users.assign-roles';

    case RolesView = 'roles.view';
    case RolesCreate = 'roles.create';
    case RolesUpdate = 'roles.update';
    case RolesDelete = 'roles.delete';
    case RolesManagePermissions = 'roles.manage-permissions';

    case SermonsView = 'sermons.view';
    case SermonsCreate = 'sermons.create';
    case SermonsUpdate = 'sermons.update';
    case SermonsDelete = 'sermons.delete';
    case SermonsPublish = 'sermons.publish';

    case EventsView = 'events.view';
    case EventsCreate = 'events.create';
    case EventsUpdate = 'events.update';
    case EventsDelete = 'events.delete';
    case EventsPublish = 'events.publish';

    case MinistriesView = 'ministries.view';
    case MinistriesCreate = 'ministries.create';
    case MinistriesUpdate = 'ministries.update';
    case MinistriesDelete = 'ministries.delete';

    case PostsView = 'posts.view';
    case PostsCreate = 'posts.create';
    case PostsUpdate = 'posts.update';
    case PostsDelete = 'posts.delete';
    case PostsPublish = 'posts.publish';

    case MediaView = 'media.view';
    case MediaUpload = 'media.upload';
    case MediaUpdate = 'media.update';
    case MediaDelete = 'media.delete';

    case PagesView = 'pages.view';
    case PagesCreate = 'pages.create';
    case PagesUpdate = 'pages.update';
    case PagesDelete = 'pages.delete';
    case PagesPublish = 'pages.publish';

    case DonationsView = 'donations.view';
    case DonationsCreate = 'donations.create';
    case DonationsUpdate = 'donations.update';
    case DonationsDelete = 'donations.delete';
    case DonationsExport = 'donations.export';

    case PrayerRequestsView = 'prayer-requests.view';
    case PrayerRequestsUpdate = 'prayer-requests.update';
    case PrayerRequestsDelete = 'prayer-requests.delete';

    case ContactSubmissionsView = 'contact-submissions.view';
    case ContactSubmissionsUpdate = 'contact-submissions.update';
    case ContactSubmissionsDelete = 'contact-submissions.delete';

    case SettingsView = 'settings.view';
    case SettingsUpdate = 'settings.update';

    case ActivityLogsView = 'activity-logs.view';

    /**
     * Get all permission names.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $permission): string => $permission->value,
            self::cases(),
        );
    }
}
