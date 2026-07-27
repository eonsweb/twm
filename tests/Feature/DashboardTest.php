<?php

use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;

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
