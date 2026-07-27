<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    Notification::fake();

    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'username' => 'john.doe_2-test',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    expect($user->username)->toBe('john.doe_2-test')
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->must_change_password)->toBeFalse()
        ->and($user->roles)->toBeEmpty();

    Notification::assertSentTo($user, VerifyEmail::class);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
});

test('registration normalizes usernames to lowercase', function () {
    $this->post(route('register.store'), [
        'name' => 'John Doe',
        'username' => 'John.Doe',
        'email' => 'john@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'john@example.com')->value('username'))
        ->toBe('john.doe');
});

test('registration requires a unique username', function () {
    User::factory()->create(['username' => 'existing-user']);

    $this->post(route('register.store'), [
        'name' => 'John Doe',
        'username' => 'existing-user',
        'email' => 'john@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrorsIn('username');
});

test('registration rejects invalid or reserved usernames', function (string $username) {
    $this->post(route('register.store'), [
        'name' => 'John Doe',
        'username' => $username,
        'email' => 'john@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrorsIn('username');
})->with([
    'spaces' => 'john doe',
    'unsupported characters' => 'john+doe',
    'too short' => 'ab',
    'reserved administrator username' => 'admin',
]);
