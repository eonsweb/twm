<?php

use App\Models\User;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('login button exposes a submission-specific loading state', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('x-data="{ submitting: false }"', escape: false)
        ->assertSee('x-on:submit="if (submitting) { $event.preventDefault() } else { submitting = true }"', escape: false)
        ->assertSee('x-bind:disabled="submitting"', escape: false)
        ->assertSee('x-show="! submitting"', escape: false)
        ->assertSee('x-show="submitting"', escape: false)
        ->assertSee('Loading...')
        ->assertSee('Sign in with a passkey');
});

test('users can authenticate with an email address', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can authenticate with a username', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'login' => strtoupper($user->username),
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('email login accepts characters that usernames do not', function () {
    $user = User::factory()->create([
        'email' => 'person+church@example.com',
    ]);

    $response = $this->post(route('login.store'), [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

test('users can not authenticate with invalid credentials', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'login' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrorsIn('login');

    $this->assertGuest();
});

test('inactive users can not authenticate', function () {
    $user = User::factory()->inactive()->create();

    $response = $this->post(route('login.store'), [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrorsIn('login');

    $this->assertGuest();
});

test('suspended users can not authenticate', function () {
    $user = User::factory()->create([
        'suspended_at' => now(),
        'suspension_reason' => 'Account review',
    ]);

    $response = $this->post(route('login.store'), [
        'login' => $user->username,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrorsIn('login');

    $this->assertGuest();
});

test('successful login records the login time', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'login' => $user->email,
        'password' => 'password',
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->last_login_at)->not->toBeNull();
});

test('successful login records the login IP address', function () {
    $user = User::factory()->create();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->post(route('login.store'), [
            'login' => $user->username,
            'password' => 'password',
        ])->assertSessionHasNoErrors();

    expect($user->refresh()->last_login_ip)->toBe('203.0.113.10');
});

test('failed login does not update login metadata', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'login' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrorsIn('login');

    $user->refresh();

    expect($user->last_login_at)->toBeNull()
        ->and($user->last_login_ip)->toBeNull();
});

test('an authenticated user is logged out after their account becomes inactive', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $user->update(['account_status' => 'inactive']);

    $this->get(route('dashboard'))
        ->assertSessionHasErrorsIn('login')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'login' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});
