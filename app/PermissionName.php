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
    case RolesAssignPermissions = 'roles.assign-permissions';

    case SermonsView = 'sermons.view';
    case SermonsCreate = 'sermons.create';
    case SermonsUpdate = 'sermons.update';
    case SermonsDelete = 'sermons.delete';
    case SermonsPublish = 'sermons.publish';
    case SermonsUnpublish = 'sermons.unpublish';
    case SermonsSchedule = 'sermons.schedule';
    case SermonsArchive = 'sermons.archive';
    case SermonsRestore = 'sermons.restore';
    case SermonsForceDelete = 'sermons.force-delete';
    case SermonsFeature = 'sermons.feature';
    case SermonSeriesManage = 'sermon-series.manage';
    case SpeakersManage = 'speakers.manage';
    case SermonTopicsManage = 'sermon-topics.manage';

    case EventsView = 'events.view';
    case EventsCreate = 'events.create';
    case EventsUpdate = 'events.update';
    case EventsDelete = 'events.delete';
    case EventsRestore = 'events.restore';
    case EventsPublish = 'events.publish';
    case EventsCancel = 'events.cancel';
    case EventTypesView = 'event-types.view';
    case EventTypesCreate = 'event-types.create';
    case EventTypesUpdate = 'event-types.update';
    case EventTypesDelete = 'event-types.delete';

    case MinistriesView = 'ministries.view';
    case MinistriesCreate = 'ministries.create';
    case MinistriesUpdate = 'ministries.update';
    case MinistriesDelete = 'ministries.delete';
    case MinistriesRestore = 'ministries.restore';
    case MinistriesForceDelete = 'ministries.force-delete';
    case MinistriesPublish = 'ministries.publish';

    case BooksView = 'books.view';
    case BooksCreate = 'books.create';
    case BooksUpdate = 'books.update';
    case BooksPublish = 'books.publish';
    case BooksArchive = 'books.archive';
    case BooksRestore = 'books.restore';
    case BooksDelete = 'books.delete';

    case LeadershipView = 'leadership.view';
    case LeadershipCreate = 'leadership.create';
    case LeadershipUpdate = 'leadership.update';
    case LeadershipDelete = 'leadership.delete';
    case LeadershipPublish = 'leadership.publish';
    case LeadershipReorder = 'leadership.reorder';

    case PostsView = 'posts.view';
    case PostsCreate = 'posts.create';
    case PostsUpdate = 'posts.update';
    case PostsDelete = 'posts.delete';
    case PostsPublish = 'posts.publish';
    case PostsRestore = 'posts.restore';
    case PostsForceDelete = 'posts.force-delete';
    case PostsArchive = 'posts.archive';
    case PostsPreview = 'posts.preview';
    case PostsManageAuthors = 'posts.manage-authors';
    case PostCategoriesManage = 'post-categories.manage';
    case PostTagsManage = 'post-tags.manage';

    case MediaView = 'media.view';
    case MediaCreate = 'media.create';
    case MediaUpload = 'media.upload';
    case MediaUpdate = 'media.update';
    case MediaDelete = 'media.delete';
    case MediaRestore = 'media.restore';
    case MediaForceDelete = 'media.force-delete';
    case MediaDownload = 'media.download';
    case MediaManageFolders = 'media.manage-folders';
    case MediaManagePrivate = 'media.manage-private';

    case PagesView = 'pages.view';
    case PagesCreate = 'pages.create';
    case PagesUpdate = 'pages.update';
    case PagesDelete = 'pages.delete';
    case PagesPublish = 'pages.publish';
    case PagesRestore = 'pages.restore';
    case PagesForceDelete = 'pages.force-delete';
    case PagesManageSections = 'pages.manage-sections';
    case PagesManageSeo = 'pages.manage-seo';
    case PagesPreview = 'pages.preview';

    case DonationsView = 'donations.view';
    case DonationsCreate = 'donations.create';
    case DonationsUpdate = 'donations.update';
    case DonationsDelete = 'donations.delete';
    case DonationsRestore = 'donations.restore';
    case DonationsApprove = 'donations.approve';
    case DonationsRefund = 'donations.refund';
    case DonationsExport = 'donations.export';
    case DonationsPrintReceipt = 'donations.print-receipt';
    case DonorsView = 'donors.view';
    case DonorsCreate = 'donors.create';
    case DonorsUpdate = 'donors.update';
    case DonorsDelete = 'donors.delete';
    case DonationCategoriesManage = 'donation-categories.manage';
    case DonationCampaignsManage = 'donation-campaigns.manage';
    case PaymentTransactionsView = 'payment-transactions.view';

    case PrayerRequestsView = 'prayer-requests.view';
    case PrayerRequestsCreate = 'prayer-requests.create';
    case PrayerRequestsUpdate = 'prayer-requests.update';
    case PrayerRequestsAssign = 'prayer-requests.assign';
    case PrayerRequestsAddNotes = 'prayer-requests.add-notes';
    case PrayerRequestsViewContactDetails = 'prayer-requests.view-contact-details';
    case PrayerRequestsViewSensitiveMetadata = 'prayer-requests.view-sensitive-metadata';
    case PrayerRequestsMarkAnswered = 'prayer-requests.mark-answered';
    case PrayerRequestsPublish = 'prayer-requests.publish';
    case PrayerRequestsArchive = 'prayer-requests.archive';
    case PrayerRequestsDelete = 'prayer-requests.delete';
    case PrayerRequestsRestore = 'prayer-requests.restore';
    case PrayerRequestsForceDelete = 'prayer-requests.force-delete';

    case ContactSubmissionsView = 'contact-submissions.view';
    case ContactSubmissionsCreate = 'contact-submissions.create';
    case ContactSubmissionsUpdate = 'contact-submissions.update';
    case ContactSubmissionsAssign = 'contact-submissions.assign';
    case ContactSubmissionsReply = 'contact-submissions.reply';
    case ContactSubmissionsResolve = 'contact-submissions.resolve';
    case ContactSubmissionsManageNotes = 'contact-submissions.manage-notes';
    case ContactSubmissionsMarkSpam = 'contact-submissions.mark-spam';
    case ContactSubmissionsDelete = 'contact-submissions.delete';
    case ContactSubmissionsRestore = 'contact-submissions.restore';
    case ContactSubmissionsForceDelete = 'contact-submissions.force-delete';

    case SettingsView = 'settings.view';
    case SettingsUpdate = 'settings.update';
    case SettingsGeneralUpdate = 'settings.general.update';
    case SettingsChurchUpdate = 'settings.church.update';
    case SettingsContactUpdate = 'settings.contact.update';
    case SettingsServiceTimesUpdate = 'settings.service-times.update';
    case SettingsBrandingUpdate = 'settings.branding.update';
    case SettingsSocialUpdate = 'settings.social.update';
    case SettingsEmailUpdate = 'settings.email.update';
    case SettingsDonationsUpdate = 'settings.donations.update';
    case SettingsIntegrationsUpdate = 'settings.integrations.update';
    case SettingsSecurityUpdate = 'settings.security.update';
    case SettingsMaintenanceUpdate = 'settings.maintenance.update';
    case SettingsLocalizationUpdate = 'settings.localization.update';

    case ActivityLogsView = 'activity-logs.view';
    case ActivityLogsViewDetails = 'activity-logs.view-details';
    case ActivityLogsExport = 'activity-logs.export';
    case ActivityLogsDelete = 'activity-logs.delete';
    case ActivityLogsPrune = 'activity-logs.prune';

    case WebsiteContentView = 'website-content.view';
    case WebsiteContentUpdate = 'website-content.update';
    case WebsiteContentPublish = 'website-content.publish';

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
