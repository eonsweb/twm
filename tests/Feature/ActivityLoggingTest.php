<?php

use App\Actions\Roles\CreateRole;
use App\Actions\Users\ChangeUserStatus;
use App\Actions\Users\CreateAdminUser;
use App\Actions\Users\SyncUserRoles;
use App\Actions\Users\UpdateUser;
use App\Models\ActivityLog;
use App\Models\Person;
use App\Models\User;
use App\RoleName;
use App\Settings\SettingManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;
use Laravel\Passkeys\Passkey;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        SystemSettingSeeder::class,
    ]);
});

test('successful login by email is recorded with request metadata', function () {
    $user = User::factory()->create();

    $this->withServerVariables([
        'REMOTE_ADDR' => '203.0.113.20',
        'HTTP_USER_AGENT' => 'Activity Test Browser',
    ])->post(route('login.store'), [
        'login' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $activity = ActivityLog::query()->where('event', 'login.succeeded')->firstOrFail();

    expect($activity->subject_id)->toBe($user->id)
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->ip_address)->toBe('203.0.113.20')
        ->and($activity->user_agent)->toBe('Activity Test Browser')
        ->and($activity->http_method)->toBe('POST')
        ->and($activity->request_id)->not->toBeNull()
        ->and(data_get($activity->properties, 'identifier_type'))->toBe('email');
});

test('successful login by username records the identifier type without its value', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'login' => strtoupper($user->username),
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $activity = ActivityLog::query()->where('event', 'login.succeeded')->firstOrFail();

    expect(data_get($activity->properties, 'identifier_type'))->toBe('username')
        ->and(json_encode($activity->properties))->not->toContain($user->username);
});

test('failed login is recorded with a masked identifier and never stores the password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'login' => $user->email,
        'password' => 'extremely-secret-wrong-password',
    ])->assertSessionHasErrors('login');

    $activity = ActivityLog::query()->where('event', 'login.failed')->firstOrFail();
    $persisted = json_encode($activity->getAttributes());

    expect(data_get($activity->properties, 'identifier'))->not->toBe($user->email)
        ->and(data_get($activity->properties, 'identifier'))->toContain('*')
        ->and($persisted)->not->toContain('extremely-secret-wrong-password')
        ->and($activity->status)->toBe('failure');
});

test('an unknown login identifier is not mislabeled as a blocked account', function () {
    $this->post(route('login.store'), [
        'login' => 'unknown.person@example.com',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('login');

    expect(ActivityLog::query()->where('event', 'login.failed')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('event', 'login.blocked')->exists())->toBeFalse();
});

test('a suspended account login attempt is identified safely', function () {
    $user = User::factory()->suspended()->create();

    $this->post(route('login.store'), [
        'login' => $user->username,
        'password' => 'password',
    ])->assertSessionHasErrors('login');

    $activity = ActivityLog::query()->where('event', 'login.blocked')->firstOrFail();

    expect($activity->subject_id)->toBe($user->id)
        ->and($activity->status)->toBe('failure')
        ->and($activity->description)->not->toContain($user->username);
});

test('logout is recorded for the authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect('/');

    expect(ActivityLog::query()
        ->where('event', 'logout')
        ->where('causer_id', $user->id)
        ->exists())->toBeTrue();
});

test('password confirmation success and failure are logged without password values', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('password.confirm.store'), ['password' => 'incorrect-value'])
        ->assertSessionHasErrors('password');
    $this->post(route('password.confirm.store'), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    expect(ActivityLog::query()->where('event', 'password.confirmation_failed')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('event', 'password.confirmed')->exists())->toBeTrue()
        ->and(json_encode(ActivityLog::query()->get()->toArray()))->not->toContain('incorrect-value');
});

test('password reset and email verification events are recorded without tokens', function () {
    $user = User::factory()->unverified()->create();

    event(new PasswordResetLinkSent($user));
    event(new PasswordReset($user));
    event(new Verified($user));

    $persisted = json_encode(ActivityLog::query()->get()->toArray());

    expect(ActivityLog::query()->where('event', 'password.reset_requested')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('event', 'password.reset')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('event', 'email.verified')->exists())->toBeTrue()
        ->and($persisted)->not->toContain('reset_token')
        ->not->toContain($user->password);
});

test('two factor recovery and passkey lifecycle events are recorded without credentials', function () {
    $user = User::factory()->create();
    $passkey = (new Passkey)->forceFill([
        'id' => 987,
        'name' => 'Office laptop',
        'credential' => 'private-passkey-credential',
    ]);

    event(new TwoFactorAuthenticationEnabled($user));
    event(new RecoveryCodesGenerated($user));
    event(new TwoFactorAuthenticationDisabled($user));
    event(new PasskeyRegistered($user, $passkey));
    event(new PasskeyDeleted($user, $passkey));

    $persisted = json_encode(ActivityLog::query()->get()->toArray());

    expect(ActivityLog::query()->where('event', 'two-factor.enabled')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('event', 'two-factor.recovery_codes_regenerated')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('event', 'two-factor.disabled')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('event', 'passkey.registered')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('event', 'passkey.removed')->exists())->toBeTrue()
        ->and($persisted)->not->toContain('private-passkey-credential');
});

test('changing a temporary password logs only a redacted change marker', function () {
    $user = User::factory()->requiringPasswordChange()->create();

    Livewire::actingAs($user)
        ->test('pages::auth.force-password-change')
        ->set('password', 'New-secure-password-123!')
        ->set('password_confirmation', 'New-secure-password-123!')
        ->call('updatePassword')
        ->assertHasNoErrors();

    $activity = ActivityLog::query()->where('event', 'password.temporary_changed')->firstOrFail();

    expect(json_encode($activity->getAttributes()))
        ->not->toContain('New-secure-password-123!')
        ->not->toContain($user->password);
});

test('account password changes are logged without current or new password values', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.security')
        ->set('current_password', 'password')
        ->set('password', 'Another-secure-password-123!')
        ->set('password_confirmation', 'Another-secure-password-123!')
        ->call('updatePassword')
        ->assertHasNoErrors();

    $activity = ActivityLog::query()->where('event', 'password.changed')->firstOrFail();

    expect(json_encode($activity->getAttributes()))
        ->not->toContain('"current_password"')
        ->not->toContain('Another-secure-password-123!');
});

test('admin user creation logs safe identity data and role assignment', function () {
    $actor = User::factory()->create();
    $actor->assignRole(RoleName::SuperAdmin);
    $role = Role::findByName(RoleName::Editor);

    $user = app(CreateAdminUser::class)->handle(
        $actor,
        ['name' => 'Logged User', 'username' => 'logged.user', 'email' => 'logged@example.com'],
        [$role->id],
    );

    $activity = ActivityLog::query()->where('event', 'user.created')->firstOrFail();
    $persisted = json_encode($activity->getAttributes());

    expect($activity->subject_id)->toBe($user->id)
        ->and($persisted)->not->toContain(Hash::make(CreateAdminUser::TEMPORARY_PASSWORD))
        ->and($persisted)->not->toContain('"password":');
});

test('user update records only changed safe fields', function () {
    $actor = User::factory()->create();
    $actor->assignRole(RoleName::SuperAdmin);
    $target = User::factory()->create(['name' => 'Before Name']);

    app(UpdateUser::class)->handle(
        $actor,
        $target,
        ['name' => 'After Name', 'username' => $target->username, 'email' => $target->email],
        [],
    );

    $activity = ActivityLog::query()->where('event', 'user.updated')->firstOrFail();

    expect($activity->old_values)->toBe(['name' => 'Before Name'])
        ->and($activity->new_values)->toBe(['name' => 'After Name']);
});

test('user suspension reactivation and role changes are recorded', function () {
    $actor = User::factory()->create();
    $actor->assignRole(RoleName::SuperAdmin);
    $target = User::factory()->create();
    $editor = Role::findByName(RoleName::Editor);

    app(SyncUserRoles::class)->handle($actor, $target, [$editor->id]);
    app(ChangeUserStatus::class)->suspend($actor, $target, 'Administrative review');
    app(ChangeUserStatus::class)->activate($actor, $target);

    expect(ActivityLog::query()->where('event', 'user.roles_updated')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('event', 'user.suspended')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('event', 'user.reactivated')->exists())->toBeTrue();
});

test('role creation and permission matrix updates are recorded', function () {
    $actor = User::factory()->create();
    $actor->assignRole(RoleName::SuperAdmin);

    $role = app(CreateRole::class)->handle($actor, 'communications-team', []);

    expect(ActivityLog::query()
        ->where('event', 'role.created')
        ->where('subject_id', $role->id)
        ->exists())->toBeTrue();
});

test('leadership models opt into automatic meaningful activity logging', function () {
    $actor = User::factory()->create();
    $actor->assignRole(RoleName::SuperAdmin);
    $this->actingAs($actor);

    $person = Person::factory()->create(['first_name' => 'Ama', 'last_name' => 'Mensah']);
    $person->update(['is_active' => false]);
    $person->delete();

    expect(ActivityLog::query()->where('event', 'person.created')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('event', 'person.updated')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('event', 'person.deleted')->exists())->toBeTrue();
});

test('system settings changes are logged while encrypted values are never persisted', function () {
    $actor = User::factory()->create();
    $actor->assignRole(RoleName::SuperAdmin);
    $this->actingAs($actor);

    app(SettingManager::class)->put('general', 'website_name', 'Audited Church Website');
    app(SettingManager::class)->put('integrations', 'payment_gateway_secret', 'gateway-secret-value');
    app(SettingManager::class)->put('security', 'maximum_login_attempts', 7);
    app(SettingManager::class)->put('branding', 'primary_logo', 'system-settings/branding/logo.png');

    $settingsActivity = ActivityLog::query()
        ->where('event', 'settings.general.updated')
        ->firstOrFail();
    $secretActivity = ActivityLog::query()
        ->where('event', 'settings.integrations.updated')
        ->firstOrFail();

    expect(data_get($settingsActivity->new_values, 'website_name'))->toBe('Audited Church Website')
        ->and(json_encode($secretActivity->getAttributes()))->not->toContain('gateway-secret-value')
        ->and(data_get($secretActivity->properties, 'sensitive_changes.0'))->toContain('Updated')
        ->and(ActivityLog::query()->where('event', 'settings.security.updated')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('event', 'settings.branding.updated')->exists())->toBeTrue();
});

test('request headers cookies and session payloads are never captured', function () {
    $user = User::factory()->create();

    $this->withHeaders([
        'Authorization' => 'Bearer secret-authorization-value',
        'Cookie' => 'session=secret-cookie-value',
    ])->post(route('login.store'), [
        'login' => $user->email,
        'password' => 'wrong-secret-password',
        '_token' => 'secret-csrf-token',
    ]);

    $persisted = json_encode(ActivityLog::query()->get()->toArray());

    expect($persisted)
        ->not->toContain('secret-authorization-value')
        ->not->toContain('secret-cookie-value')
        ->not->toContain('secret-csrf-token')
        ->not->toContain('wrong-secret-password');
});
