<?php

use App\PermissionName;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'account.active', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')
        ->middleware(PermissionMiddleware::using(PermissionName::DashboardView))
        ->name('dashboard');
});

require __DIR__.'/settings.php';
