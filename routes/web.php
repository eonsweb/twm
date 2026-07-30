<?php

use App\Http\Controllers\ActivityLogExportController;
use App\Http\Controllers\MediaDownloadController;
use App\Http\Controllers\MediaPreviewController;
use App\Http\Middleware\EnforcePublicWebsiteAvailability;
use App\PermissionName;
use App\SystemSettingSection;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::view('/', 'welcome')
    ->middleware(EnforcePublicWebsiteAvailability::class)
    ->name('home');

Route::middleware(EnforcePublicWebsiteAvailability::class)->group(function (): void {
    Route::livewire('sermons', 'pages::public.sermons.index')->name('public.sermons.index');
    Route::livewire('sermons/{sermon:slug}', 'pages::public.sermons.show')->name('public.sermons.show');
    Route::livewire('sermon-series/{series:slug}', 'pages::public.sermon-series.show')->name('public.sermon-series.show');
    Route::livewire('speakers/{speaker:slug}', 'pages::public.speakers.show')->name('public.speakers.show');
    Route::livewire('events', 'pages::public.events.index')->name('public.events.index');
    Route::livewire('events/{event:slug}', 'pages::public.events.show')->name('public.events.show');
    Route::livewire('ministries', 'pages::public.ministries.index')->name('public.ministries.index');
    Route::livewire('ministries/{ministry:slug}', 'pages::public.ministries.show')->name('public.ministries.show');
    Route::livewire('blog', 'pages::public.blog.index')->name('blog.index');
    Route::livewire('blog/category/{value}', 'pages::public.blog.archive')->defaults('type', 'category')->name('blog.category');
    Route::livewire('blog/tag/{value}', 'pages::public.blog.archive')->defaults('type', 'tag')->name('blog.tag');
    Route::livewire('blog/author/{value}', 'pages::public.blog.archive')->defaults('type', 'author')->name('blog.author');
    Route::livewire('blog/search/{value}', 'pages::public.blog.archive')->defaults('type', 'search')->name('blog.search');
    Route::livewire('blog/{post:slug}', 'pages::public.blog.show')->name('blog.show');
});

Route::livewire('password/change-required', 'pages::auth.force-password-change')
    ->middleware(['auth', 'account.active'])
    ->name('password.change.required');

Route::middleware(['auth', 'account.active', 'password.changed', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')
        ->middleware(PermissionMiddleware::using(PermissionName::DashboardView))
        ->name('dashboard');

    Route::livewire('users', 'pages::users.index')
        ->middleware(PermissionMiddleware::using(PermissionName::UsersView))
        ->name('users.index');
    Route::livewire('users/create', 'pages::users.create')
        ->middleware(PermissionMiddleware::using(PermissionName::UsersCreate))
        ->name('users.create');
    Route::livewire('users/{user}', 'pages::users.show')
        ->middleware(PermissionMiddleware::using(PermissionName::UsersView))
        ->name('users.show');
    Route::livewire('users/{user}/edit', 'pages::users.edit')
        ->middleware(PermissionMiddleware::using(PermissionName::UsersUpdate))
        ->name('users.edit');

    Route::name('admin.')->group(function () {
        Route::livewire('roles', 'pages::roles.index')
            ->middleware(PermissionMiddleware::using(PermissionName::RolesView))
            ->name('roles.index');
        Route::livewire('roles/create', 'pages::roles.create')
            ->middleware(PermissionMiddleware::using(PermissionName::RolesCreate))
            ->name('roles.create');
        Route::livewire('roles/{role}/edit', 'pages::roles.edit')
            ->middleware(PermissionMiddleware::using(PermissionName::RolesUpdate))
            ->name('roles.edit');

        Route::prefix('admin/settings')->name('settings.')->group(function (): void {
            Route::redirect('/', '/admin/settings/general')
                ->middleware(PermissionMiddleware::using(PermissionName::SettingsView))
                ->name('index');

            foreach (SystemSettingSection::cases() as $section) {
                $route = Route::livewire($section->value, 'pages::admin.settings.show')
                    ->defaults('section', $section->value)
                    ->middleware([
                        PermissionMiddleware::using(PermissionName::SettingsView),
                        PermissionMiddleware::using($section->permission()),
                    ])
                    ->name($section->value);

                if ($section->requiresPasswordConfirmation()) {
                    $route->middleware('password.confirm');
                }
            }
        });
    });

    Route::livewire('leadership', 'pages::leadership.index')
        ->middleware(PermissionMiddleware::using(PermissionName::LeadershipView))
        ->name('leadership.index');
    Route::livewire('leadership/create', 'pages::leadership.create')
        ->middleware(PermissionMiddleware::using(PermissionName::LeadershipCreate))
        ->name('leadership.create');
    Route::livewire('leadership/positions', 'pages::leadership.positions')
        ->middleware(PermissionMiddleware::using(PermissionName::LeadershipView))
        ->name('leadership.positions');
    Route::livewire('leadership/ordering', 'pages::leadership.ordering')
        ->middleware(PermissionMiddleware::using(PermissionName::LeadershipReorder))
        ->name('leadership.ordering');
    Route::livewire('leadership/{person}/edit', 'pages::leadership.edit')
        ->middleware(PermissionMiddleware::using(PermissionName::LeadershipUpdate))
        ->name('leadership.edit');

    Route::livewire('admin/activity-logs', 'pages::activity-logs.index')
        ->middleware(PermissionMiddleware::using(PermissionName::ActivityLogsView))
        ->name('activity-logs.index');
    Route::get('admin/activity-logs/export', ActivityLogExportController::class)
        ->middleware([
            PermissionMiddleware::using(PermissionName::ActivityLogsExport),
            'password.confirm',
        ])
        ->name('activity-logs.export');

    Route::prefix('admin')->group(function (): void {
        Route::livewire('media/trash', 'pages::media.index')
            ->defaults('trash', true)
            ->middleware(PermissionMiddleware::using(PermissionName::MediaRestore))
            ->name('media.trash');
        Route::livewire('media', 'pages::media.index')
            ->middleware(PermissionMiddleware::using(PermissionName::MediaView))
            ->name('media.index');
        Route::get('media/{media}/download', MediaDownloadController::class)
            ->middleware(PermissionMiddleware::using(PermissionName::MediaDownload))
            ->name('media.download');
        Route::get('media/{media}/preview', MediaPreviewController::class)
            ->middleware(PermissionMiddleware::using(PermissionName::MediaView))
            ->name('media.preview');
        Route::livewire('sermons', 'pages::sermons.index')
            ->middleware(PermissionMiddleware::using(PermissionName::SermonsView))
            ->name('sermons.index');
        Route::livewire('sermons/create', 'pages::sermons.create')
            ->middleware(PermissionMiddleware::using(PermissionName::SermonsCreate))
            ->name('sermons.create');
        Route::livewire('sermons/{sermon:slug}/edit', 'pages::sermons.edit')
            ->middleware(PermissionMiddleware::using(PermissionName::SermonsUpdate))
            ->name('sermons.edit');
        Route::livewire('sermons/{sermon:slug}', 'pages::sermons.show')
            ->middleware(PermissionMiddleware::using(PermissionName::SermonsView))
            ->name('sermons.show');
        Route::livewire('sermon-series', 'pages::sermon-series.index')
            ->middleware(PermissionMiddleware::using(PermissionName::SermonsView))
            ->name('sermon-series.index');
        Route::livewire('speakers', 'pages::speakers.index')
            ->middleware(PermissionMiddleware::using(PermissionName::SermonsView))
            ->name('speakers.index');
        Route::livewire('sermon-topics', 'pages::topics.index')
            ->middleware(PermissionMiddleware::using(PermissionName::SermonsView))
            ->name('sermon-topics.index');
        Route::livewire('events', 'pages::events.index')
            ->middleware(PermissionMiddleware::using(PermissionName::EventsView))
            ->name('events.index');
        Route::livewire('events/create', 'pages::events.create')
            ->middleware(PermissionMiddleware::using(PermissionName::EventsCreate))
            ->name('events.create');
        Route::livewire('events/{event:slug}/edit', 'pages::events.edit')
            ->middleware(PermissionMiddleware::using(PermissionName::EventsUpdate))
            ->name('events.edit');
        Route::livewire('events/{event:slug}', 'pages::events.show')
            ->middleware(PermissionMiddleware::using(PermissionName::EventsView))
            ->name('events.show');
        Route::livewire('event-types', 'pages::event-types.index')
            ->middleware(PermissionMiddleware::using(PermissionName::EventTypesView))
            ->name('event-types.index');
        Route::livewire('ministries', 'pages::ministries.index')
            ->middleware(PermissionMiddleware::using(PermissionName::MinistriesView))
            ->name('ministries.index');
        Route::livewire('ministries/create', 'pages::ministries.create')
            ->middleware(PermissionMiddleware::using(PermissionName::MinistriesCreate))
            ->name('ministries.create');
        Route::livewire('ministries/{ministry:slug}/edit', 'pages::ministries.edit')
            ->middleware(PermissionMiddleware::using(PermissionName::MinistriesUpdate))
            ->name('ministries.edit');
        Route::livewire('ministries/{ministry:slug}', 'pages::ministries.show')
            ->middleware(PermissionMiddleware::using(PermissionName::MinistriesView))
            ->name('ministries.show');
        Route::livewire('posts', 'pages::posts.index')
            ->middleware(PermissionMiddleware::using(PermissionName::PostsView))
            ->name('posts.index');
        Route::livewire('posts/create', 'pages::posts.create')
            ->middleware(PermissionMiddleware::using(PermissionName::PostsCreate))
            ->name('posts.create');
        Route::livewire('posts/{post:slug}/edit', 'pages::posts.edit')
            ->middleware(PermissionMiddleware::using(PermissionName::PostsUpdate))
            ->name('posts.edit');
        Route::livewire('posts/{post:slug}', 'pages::posts.show')
            ->middleware(PermissionMiddleware::using(PermissionName::PostsView))
            ->name('posts.show');
        Route::livewire('post-categories', 'pages::post-categories.index')
            ->middleware(PermissionMiddleware::using(PermissionName::PostCategoriesManage))
            ->name('post-categories.index');
        Route::livewire('post-tags', 'pages::post-tags.index')
            ->middleware(PermissionMiddleware::using(PermissionName::PostTagsManage))
            ->name('post-tags.index');
        Route::livewire('blog-preview/{post:slug}', 'pages::public.blog.preview')
            ->middleware(['signed', PermissionMiddleware::using(PermissionName::PostsPreview)])
            ->name('blog.preview');
    });
});

require __DIR__.'/settings.php';
