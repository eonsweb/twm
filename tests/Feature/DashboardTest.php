<?php

use App\Models\Media;
use App\Models\User;
use App\RoleName;
use App\Settings\SettingManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authorized users can visit the dashboard', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $user = User::factory()->create();
    $user->assignRole(RoleName::Editor);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response
        ->assertOk()
        ->assertSee('Welcome back')
        ->assertSee('Website status')
        ->assertSee('Triumphant World Ministry');
});

test('unauthorized users can not visit the dashboard', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertForbidden();
});

test('dashboard content and navigation respect module permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $financeOfficer = User::factory()->create();
    $financeOfficer->assignRole(RoleName::FinanceOfficer);

    $this->actingAs($financeOfficer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Recent donations')
        ->assertDontSee('Recent sermons')
        ->assertDontSee('Upcoming events')
        ->assertDontSee('Roles &amp; permissions', false);
});

test('sidebar branding displays only the configured admin dashboard logo', function () {
    Storage::fake('public');
    $media = Media::factory()->create();
    Storage::disk($media->disk)->put($media->path, 'logo');
    app(SettingManager::class)->put('church', 'official_name', 'Triumphant World Ministry');
    app(SettingManager::class)->put('branding', 'admin_logo', $media->id);

    $this->blade('<x-app-logo :sidebar="true" href="#" />')
        ->assertSee('data-test="sidebar-branding-logo"', false)
        ->assertSee(Storage::disk($media->disk)->url($media->path), false)
        ->assertSee('alt="Triumphant World Ministry Administration"', false)
        ->assertDontSee('data-test="sidebar-branding-fallback"', false);
});

test('sidebar branding displays the existing text when no admin dashboard logo is configured', function () {
    app(SettingManager::class)->put('church', 'official_name', 'Triumphant World Ministry');

    $this->blade('<x-app-logo :sidebar="true" href="#" />')
        ->assertSee('data-test="sidebar-branding-fallback"', false)
        ->assertSee('Triumphant World Ministry')
        ->assertSee('Administration')
        ->assertDontSee('data-test="sidebar-branding-logo"', false);
});

test('sidebar branding falls back to text when admin dashboard logo media is unavailable', function () {
    Storage::fake('public');
    $settings = app(SettingManager::class);
    $settings->put('church', 'official_name', 'Triumphant World Ministry');
    $settings->put('branding', 'admin_logo', 999999);

    $this->blade('<x-app-logo :sidebar="true" href="#" />')
        ->assertSee('data-test="sidebar-branding-fallback"', false)
        ->assertDontSee('data-test="sidebar-branding-logo"', false);

    $missingFile = Media::factory()->create();
    $settings->put('branding', 'admin_logo', $missingFile->id);

    $this->blade('<x-app-logo :sidebar="true" href="#" />')
        ->assertSee('data-test="sidebar-branding-fallback"', false)
        ->assertDontSee('data-test="sidebar-branding-logo"', false);
});
