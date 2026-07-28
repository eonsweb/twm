<?php

use App\Http\Controllers\ActivityLogExportController;
use App\Http\Middleware\EnforcePublicWebsiteAvailability;
use App\PermissionName;
use App\SystemSettingSection;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::view('/', 'welcome')
    ->middleware(EnforcePublicWebsiteAvailability::class)
    ->name('home');

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
});

require __DIR__.'/settings.php';
