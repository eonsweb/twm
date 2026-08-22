<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="font-body min-h-screen bg-slate-50 text-slate-950 antialiased dark:bg-zinc-950 dark:text-white">
        <a
            href="#admin-main"
            class="fixed start-4 top-4 z-50 -translate-y-24 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-church-maroon-900 shadow-lg transition focus:translate-y-0 dark:bg-zinc-800 dark:text-white"
        >
            {{ __('Skip to main content') }}
        </a>

        <flux:sidebar
            sticky
            collapsible="mobile"
            class="admin-sidebar w-64! border-0! bg-church-maroon-950! text-white"
        >
            <flux:sidebar.header class="px-2 pb-6 pt-3">
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden text-white" />
            </flux:sidebar.header>

            <flux:sidebar.nav class="gap-5 px-1">
                <flux:sidebar.group :heading="__('Main')" class="admin-sidebar-group grid">
                    <x-admin.nav-item
                        :label="__('Dashboard')"
                        icon="home"
                        :permission="\App\PermissionName::DashboardView->value"
                        route-name="dashboard"
                        active-pattern="dashboard"
                    />
                </flux:sidebar.group>

                @canany([
                    \App\PermissionName::SermonsView->value,
                    \App\PermissionName::EventsView->value,
                    \App\PermissionName::ServiceSchedulesView->value,
                    \App\PermissionName::MinistriesView->value,
                    \App\PermissionName::BooksView->value,
                    \App\PermissionName::LeadershipView->value,
                    \App\PermissionName::MediaView->value,
                    \App\PermissionName::PagesView->value,
                ])
                    <flux:sidebar.group :heading="__('Content management')" class="admin-sidebar-group grid">
                        <x-admin.nav-item :label="__('Sermons')" icon="play-circle" :permission="\App\PermissionName::SermonsView->value" route-name="sermons.index" active-pattern="sermons.*" />
                        <x-admin.nav-item :label="__('Sermon speakers')" icon="microphone" :permission="\App\PermissionName::SermonsView->value" route-name="speakers.index" active-pattern="speakers.*" />
                        <x-admin.nav-item :label="__('Events')" icon="calendar-days" :permission="\App\PermissionName::EventsView->value" route-name="events.index" active-pattern="events.*" />
                        <x-admin.nav-item :label="__('Event types')" icon="tag" :permission="\App\PermissionName::EventTypesView->value" route-name="event-types.index" active-pattern="event-types.*" />
                        <x-admin.nav-item :label="__('Service Schedules')" icon="clock" :permission="\App\PermissionName::ServiceSchedulesView->value" route-name="service-schedules.index" active-pattern="service-schedules.*" />
                        <x-admin.nav-item :label="__('Ministries')" icon="user-group" :permission="\App\PermissionName::MinistriesView->value" route-name="ministries.index" active-pattern="ministries.*" />
                        <x-admin.nav-item :label="__('Books')" icon="book-open" :permission="\App\PermissionName::BooksView->value" route-name="books.index" active-pattern="books.*" />
                        <x-admin.nav-item :label="__('Leadership')" icon="identification" :permission="\App\PermissionName::LeadershipView->value" route-name="leadership.index" active-pattern="leadership.*" />
                        <x-admin.nav-item :label="__('Media library')" icon="photo" :permission="\App\PermissionName::MediaView->value" route-name="media.index" active-pattern="media.*" />
                        <x-admin.nav-item :label="__('Pages')" icon="document-duplicate" :permission="\App\PermissionName::PagesView->value" route-name="pages.index" active-pattern="pages.*" />
                    </flux:sidebar.group>
                @endcanany

                @canany([
                    \App\PermissionName::PostsView->value,
                    \App\PermissionName::PostsCreate->value,
                    \App\PermissionName::PostCategoriesManage->value,
                    \App\PermissionName::PostTagsManage->value,
                ])
                    <flux:sidebar.group :heading="__('Blog')" class="admin-sidebar-group grid">
                        <x-admin.nav-item :label="__('All posts')" icon="document-text" :permission="\App\PermissionName::PostsView->value" route-name="posts.index" active-pattern="posts.*" />
                        <x-admin.nav-item :label="__('Add new post')" icon="document-plus" :permission="\App\PermissionName::PostsCreate->value" route-name="posts.create" active-pattern="posts.create" />
                        <x-admin.nav-item :label="__('Categories')" icon="folder" :permission="\App\PermissionName::PostCategoriesManage->value" route-name="post-categories.index" active-pattern="post-categories.*" />
                        <x-admin.nav-item :label="__('Tags')" icon="tag" :permission="\App\PermissionName::PostTagsManage->value" route-name="post-tags.index" active-pattern="post-tags.*" />
                        @can(\App\PermissionName::PostsView->value)
                            <flux:sidebar.item icon="trash" :href="route('posts.index', ['status' => 'deleted'])" :current="request()->routeIs('posts.index') && request('status') === 'deleted'" class="admin-sidebar-item" wire:navigate>
                                {{ __('Trash') }}
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcanany

                @canany([
                    \App\PermissionName::DonationsView->value,
                    \App\PermissionName::PrayerRequestsView->value,
                    \App\PermissionName::ContactSubmissionsView->value,
                ])
                    <flux:sidebar.group :heading="__('Engagement')" class="admin-sidebar-group grid">
                        <x-admin.nav-item :label="__('Donations')" icon="heart" :permission="\App\PermissionName::DonationsView->value" route-name="donations.index" active-pattern="donations.*" />
                        <x-admin.nav-item :label="__('Donors')" icon="users" :permission="\App\PermissionName::DonorsView->value" route-name="donors.index" active-pattern="donors.*" />
                        <x-admin.nav-item :label="__('Giving categories')" icon="tag" :permission="\App\PermissionName::DonationCategoriesManage->value" route-name="donation-categories.index" active-pattern="donation-categories.*" />
                        <x-admin.nav-item :label="__('Campaigns')" icon="flag" :permission="\App\PermissionName::DonationCampaignsManage->value" route-name="donation-campaigns.index" active-pattern="donation-campaigns.*" />
                        <x-admin.nav-item :label="__('Transactions')" icon="banknotes" :permission="\App\PermissionName::PaymentTransactionsView->value" route-name="payment-transactions.index" active-pattern="payment-transactions.*" />
                        <x-admin.nav-item :label="__('Prayer requests')" icon="hand-raised" :permission="\App\PermissionName::PrayerRequestsView->value" route-name="prayer-requests.index" active-pattern="prayer-requests.*" />
                        <x-admin.nav-item :label="__('Contact submissions')" icon="envelope" :permission="\App\PermissionName::ContactSubmissionsView->value" route-name="contact-submissions.index" active-pattern="contact-submissions.*" />
                    </flux:sidebar.group>
                @endcanany

                @canany([
                    \App\PermissionName::UsersView->value,
                    \App\PermissionName::RolesView->value,
                ])
                    <flux:sidebar.group :heading="__('Administration')" class="admin-sidebar-group grid">
                        <x-admin.nav-item :label="__('Users')" icon="users" :permission="\App\PermissionName::UsersView->value" route-name="users.index" active-pattern="users.*" />
                        <x-admin.nav-item :label="__('Roles & permissions')" icon="key" :permission="\App\PermissionName::RolesView->value" route-name="admin.roles.index" active-pattern="admin.roles.*" />
                    </flux:sidebar.group>
                @endcanany

                @canany([
                    \App\PermissionName::SettingsView->value,
                    \App\PermissionName::ActivityLogsView->value,
                ])
                    <flux:sidebar.group :heading="__('System')" class="admin-sidebar-group grid">
                        <x-admin.nav-item :label="__('Settings')" icon="cog-6-tooth" :permission="\App\PermissionName::SettingsView->value" route-name="admin.settings.general" active-pattern="admin.settings.*" />
                        <x-admin.nav-item :label="__('Activity logs')" icon="clipboard-document-list" :permission="\App\PermissionName::ActivityLogsView->value" route-name="activity-logs.index" active-pattern="activity-logs.*" />
                    </flux:sidebar.group>
                @endcanany
            </flux:sidebar.nav>

            <flux:sidebar.spacer />

            <div class="mx-2 mb-3 rounded-xl border border-emerald-300/20 bg-emerald-700/80 p-4 text-white shadow-lg shadow-black/10">
                <p class="text-sm font-semibold">{{ __('Need help?') }}</p>
                <p class="mt-1 text-xs leading-5 text-emerald-50/90">
                    {{ __('View the public website or manage your account settings.') }}
                </p>
                <a
                    href="{{ route('home') }}"
                    class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-church-gold-500 px-3 py-2.5 text-xs font-semibold text-church-maroon-950 transition hover:bg-church-gold-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                    wire:navigate
                >
                    {{ __('View church website') }}
                    <flux:icon.arrow-top-right-on-square class="size-3.5" />
                </a>
            </div>
        </flux:sidebar>

        <flux:header
            class="sticky top-0 z-30 min-h-16 border-b border-slate-200/80 bg-white/95 px-4 backdrop-blur sm:px-6 lg:px-8 dark:border-zinc-800 dark:bg-zinc-950/95"
        >
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" aria-label="{{ __('Open navigation') }}" />

            <div class="ms-2 lg:hidden">
                <span class="block text-xs font-semibold leading-tight text-church-maroon-900 dark:text-white">
                    {{ __('Triumphant World Ministry') }}
                </span>
                <span class="block text-[0.65rem] text-slate-500 dark:text-zinc-400">
                    {{ __('Administration') }}
                </span>
            </div>

            <flux:spacer />

            <div class="hidden w-full max-w-72 md:block">
                <flux:input
                    as="button"
                    icon="magnifying-glass"
                    :placeholder="__('Search administration…')"
                    kbd="Ctrl K"
                    disabled
                    aria-label="{{ __('Search administration, coming soon') }}"
                />
            </div>

            <div class="ms-2 flex items-center gap-1 sm:ms-4 sm:gap-2">
                <flux:button
                    type="button"
                    variant="subtle"
                    icon="bell"
                    tooltip="{{ __('Notifications coming soon') }}"
                    aria-label="{{ __('Notifications') }}"
                    disabled
                />

                <x-desktop-user-menu />
            </div>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
