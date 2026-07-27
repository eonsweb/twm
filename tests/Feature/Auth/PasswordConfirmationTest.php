<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('guests cannot access the confirm password screen', function () {
    $this->get(route('password.confirm'))
        ->assertRedirect(route('login'));
});

test('confirm password screen can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('password.confirm'));

    $response->assertOk();
});

test('an authenticated user can confirm their password without a login field', function () {
    $user = User::factory()->create();
    $queries = collect();

    DB::listen(function ($query) use ($queries): void {
        $queries->push($query->sql);
    });

    $this->actingAs($user)
        ->withSession([
            '_token' => 'test-token',
            'url.intended' => route('security.edit'),
        ])
        ->post(route('password.confirm.store'), [
            '_token' => 'test-token',
            'password' => 'password',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('auth.password_confirmed_at')
        ->assertRedirect(route('security.edit'));

    expect($queries->contains(
        fn (string $query): bool => str_contains($query, 'login'),
    ))->toBeFalse();
});

test('password confirmation uses the session user and ignores login input', function () {
    $authenticatedUser = User::factory()->create();
    $otherUser = User::factory()->create([
        'password' => 'another-password',
    ]);

    $this->actingAs($authenticatedUser)
        ->withSession(['_token' => 'test-token'])
        ->post(route('password.confirm.store'), [
            '_token' => 'test-token',
            'login' => $otherUser->email,
            'password' => 'password',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('auth.password_confirmed_at');
});

test('an incorrect password returns a generic validation error', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['_token' => 'test-token'])
        ->post(route('password.confirm.store'), [
            '_token' => 'test-token',
            'password' => 'incorrect-password',
        ])
        ->assertSessionHasErrors('password')
        ->assertSessionMissing('auth.password_confirmed_at');
});

test('an empty password returns a validation error', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['_token' => 'test-token'])
        ->post(route('password.confirm.store'), [
            '_token' => 'test-token',
            'password' => '',
        ])
        ->assertSessionHasErrors('password')
        ->assertSessionMissing('auth.password_confirmed_at');
});

test('a confirmed user can access a password protected route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['_token' => 'test-token'])
        ->post(route('password.confirm.store'), [
            '_token' => 'test-token',
            'password' => 'password',
        ])
        ->assertSessionHasNoErrors();

    $this->get(route('security.edit'))->assertOk();
});

test('password confirmation expires after the configured timeout', function () {
    $user = User::factory()->create();
    $expiredConfirmation = now()->subSeconds(config('auth.password_timeout') + 1)->timestamp;

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => $expiredConfirmation])
        ->get(route('security.edit'))
        ->assertRedirect(route('password.confirm'));
});
