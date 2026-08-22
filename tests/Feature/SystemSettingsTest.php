<?php

use App\Mail\SettingsTestMail;
use App\Models\ServiceSchedule;
use App\Models\SystemSetting;
use App\Models\User;
use App\PermissionName;
use App\RoleName;
use App\Settings\SettingManager;
use App\Settings\SettingRegistry;
use App\SystemSettingSection;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ServiceScheduleSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([
        RolesAndPermissionsSeeder::class,
        SystemSettingSeeder::class,
        ServiceScheduleSeeder::class,
    ]);
});

function settingsUser(RoleName $role = RoleName::SuperAdmin): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('default settings and service schedules are seeded idempotently', function () {
    $initialSettingCount = SystemSetting::query()->count();

    $this->seed(SystemSettingSeeder::class);
    $this->seed(ServiceScheduleSeeder::class);

    expect(SystemSetting::query()->count())->toBe($initialSettingCount)
        ->and(SystemSetting::query()->where('group', 'general')->where('key', 'website_name')->exists())->toBeTrue()
        ->and(ServiceSchedule::query()->count())->toBe(4);
});

test('the defaults initializer never overwrites administrator changes', function () {
    app(SettingManager::class)->put('general', 'website_name', 'Administrator Choice');

    $this->seed(SystemSettingSeeder::class);

    expect(app(SettingManager::class)->get('general', 'website_name'))->toBe('Administrator Choice');
});

test('the settings manager casts values and invalidates its group cache', function () {
    $settings = app(SettingManager::class);
    $definition = collect(app(SettingRegistry::class)->forSection(SystemSettingSection::General))
        ->firstWhere('key', 'pagination_size');

    expect($settings->get('general', 'pagination_size'))->toBe(15);

    $settings->putMany([[...$definition, 'value' => 30]]);

    expect($settings->get('general', 'pagination_size'))->toBe(30);
});

test('secrets are encrypted at rest omitted from public settings and never serialized', function () {
    $settings = app(SettingManager::class);
    $definition = collect(app(SettingRegistry::class)->forSection(SystemSettingSection::Integrations))
        ->firstWhere('key', 'payment_gateway_secret');

    $settings->putMany([[...$definition, 'value' => 'top-secret-value']]);

    $stored = SystemSetting::query()
        ->where('group', 'integrations')
        ->where('key', 'payment_gateway_secret')
        ->firstOrFail();

    expect($stored->getRawOriginal('value'))->not->toContain('top-secret-value')
        ->and(Crypt::decryptString($stored->getRawOriginal('value')))->toBe('top-secret-value')
        ->and($stored->toArray())->not->toHaveKey('value');

    $stored->forceFill(['is_public' => true])->save();
    $settings->forgetGroup('integrations');

    expect($settings->publicGroup('integrations'))->not->toHaveKey('payment_gateway_secret');
});

test('public settings expose only explicitly public non-secret values', function () {
    $publicSettings = app(SettingManager::class)->publicGroup('general');

    expect($publicSettings)
        ->toHaveKey('website_name')
        ->not->toHaveKey('admin_notification_email');
});

test('authorized administrators can update a general settings section', function () {
    $user = settingsUser(RoleName::Administrator);

    Livewire::actingAs($user)
        ->test('settings.editor', ['section' => 'general'])
        ->set('values.website_name', 'New Church Website')
        ->set('values.pagination_size', 25)
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingManager::class)->get('general', 'website_name'))->toBe('New Church Website')
        ->and(app(SettingManager::class)->get('general', 'pagination_size'))->toBe(25);
});

test('church and contact settings can be saved by an authorized editor', function () {
    $user = settingsUser(RoleName::Editor);

    Livewire::actingAs($user)
        ->test('settings.editor', ['section' => 'church'])
        ->set('values.motto', 'Faith, hope, and love')
        ->set('values.lead_pastor_name', 'Pastor Example')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::actingAs($user)
        ->test('settings.editor', ['section' => 'contact'])
        ->set('values.primary_email', 'hello@example.com')
        ->set('values.primary_phone', '+233 20 123 4567')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingManager::class)->get('church', 'motto'))->toBe('Faith, hope, and love')
        ->and(app(SettingManager::class)->get('contact', 'primary_email'))->toBe('hello@example.com');
});

test('social media urls are validated', function () {
    $user = settingsUser(RoleName::MediaManager);

    Livewire::actingAs($user)
        ->test('settings.editor', ['section' => 'social'])
        ->set('values.facebook_url', 'not-a-url')
        ->call('save')
        ->assertHasErrors(['values.facebook_url']);
});

test('invalid section values are rejected without changing stored settings', function () {
    $user = settingsUser();
    $original = app(SettingManager::class)->get('general', 'website_url');

    Livewire::actingAs($user)
        ->test('settings.editor', ['section' => 'general'])
        ->set('values.website_url', 'not-a-url')
        ->set('values.pagination_size', 500)
        ->call('save')
        ->assertHasErrors(['values.website_url', 'values.pagination_size']);

    expect(app(SettingManager::class)->get('general', 'website_url'))->toBe($original);
});

test('blank secret fields preserve existing encrypted values', function () {
    $user = settingsUser();
    $this->withSession(['auth.password_confirmed_at' => now()->timestamp]);

    Livewire::actingAs($user)
        ->test('settings.editor', ['section' => 'integrations'])
        ->set('values.payment_gateway_secret', 'first-secret')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('values.payment_gateway_secret', '');

    Livewire::actingAs($user)
        ->test('settings.editor', ['section' => 'integrations'])
        ->assertSet('values.payment_gateway_secret', '')
        ->assertDontSee('first-secret')
        ->set('values.analytics_id', 'analytics-123')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingManager::class)->get('integrations', 'payment_gateway_secret'))->toBe('first-secret');
});

test('json settings are validated and returned as arrays', function () {
    $user = settingsUser(RoleName::FinanceOfficer);

    Livewire::actingAs($user)
        ->test('settings.editor', ['section' => 'donations'])
        ->set('values.suggested_amounts', '[10, 20, 50]')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingManager::class)->get('donations', 'suggested_amounts'))->toBe([10, 20, 50]);
});

test('suggested donation amounts must all be positive numbers', function () {
    $user = settingsUser(RoleName::FinanceOfficer);

    Livewire::actingAs($user)
        ->test('settings.editor', ['section' => 'donations'])
        ->set('values.suggested_amounts', '[10, -20]')
        ->call('save')
        ->assertHasErrors(['values.suggested_amounts']);
});

test('email settings can send a test message without exposing credentials', function () {
    Mail::fake();
    $user = settingsUser(RoleName::Administrator);
    $this->withSession(['auth.password_confirmed_at' => now()->timestamp]);

    Livewire::actingAs($user)
        ->test('settings.editor', ['section' => 'email'])
        ->set('testRecipient', 'test-recipient@example.com')
        ->call('sendTestEmail')
        ->assertHasNoErrors();

    Mail::assertSent(SettingsTestMail::class, fn (SettingsTestMail $mail): bool => $mail->hasTo('test-recipient@example.com'));
});

test('sensitive Livewire actions reject an expired password confirmation', function () {
    $user = settingsUser();

    Livewire::actingAs($user)
        ->test('settings.editor', ['section' => 'integrations'])
        ->set('values.analytics_id', 'should-not-save')
        ->call('save')
        ->assertForbidden();

    expect(app(SettingManager::class)->get('integrations', 'analytics_id'))->toBe('');
});

test('service schedules can be updated and reordered atomically', function () {
    $user = settingsUser(RoleName::Editor);

    $component = Livewire::actingAs($user)
        ->test('settings.service-times')
        ->set('schedules.0.name', 'Celebration Service')
        ->set('schedules.0.start_time', '09:30')
        ->call('removeSchedule', 3)
        ->call('removeSchedule', 2)
        ->call('removeSchedule', 1)
        ->call('addSchedule');

    $newIndex = count($component->get('schedules')) - 1;

    $component
        ->set("schedules.{$newIndex}.name", 'Evening Prayer')
        ->set("schedules.{$newIndex}.day_of_week", 'Friday')
        ->set("schedules.{$newIndex}.start_time", '18:00')
        ->call('save')
        ->assertHasNoErrors();

    expect(ServiceSchedule::query()->count())->toBe(2)
        ->and(ServiceSchedule::query()->orderBy('display_order')->pluck('name')->all())
        ->toBe(['Celebration Service', 'Evening Prayer']);
});

test('branding uploads are stored and old unreferenced files are removed', function () {
    Storage::fake('public');
    $user = settingsUser(RoleName::MediaManager);

    Livewire::actingAs($user)
        ->test('settings.branding')
        ->set('uploads.primary_logo', UploadedFile::fake()->image('first-logo.png', 800, 400))
        ->call('save')
        ->assertHasNoErrors();

    $firstPath = app(SettingManager::class)->get('branding', 'primary_logo');
    Storage::disk('public')->assertExists($firstPath);

    Livewire::actingAs($user)
        ->test('settings.branding')
        ->set('uploads.primary_logo', UploadedFile::fake()->image('replacement.png', 800, 400))
        ->call('save')
        ->assertHasNoErrors();

    $replacementPath = app(SettingManager::class)->get('branding', 'primary_logo');
    Storage::disk('public')->assertExists($replacementPath);
    Storage::disk('public')->assertMissing($firstPath);
});

test('branding rejects unsupported or oversized uploads', function () {
    Storage::fake('public');
    $user = settingsUser(RoleName::MediaManager);

    Livewire::actingAs($user)
        ->test('settings.branding')
        ->set('uploads.primary_logo', UploadedFile::fake()->image('unsupported.gif'))
        ->call('save')
        ->assertHasErrors(['uploads.primary_logo']);
});

test('system settings routes enforce authentication and granular permissions', function () {
    $this->get(route('admin.settings.general'))->assertRedirect(route('login'));

    $user = User::factory()->create();
    $this->actingAs($user)->get(route('admin.settings.general'))->assertForbidden();

    $editor = settingsUser(RoleName::Editor);
    $this->actingAs($editor)
        ->get(route('admin.settings.church'))
        ->assertOk();
    $this->actingAs($editor)
        ->get(route('admin.settings.donations'))
        ->assertForbidden();
});

test('sensitive settings routes require a recent password confirmation', function () {
    $user = settingsUser();

    $this->actingAs($user)
        ->get(route('admin.settings.integrations'))
        ->assertRedirect(route('password.confirm'));

    $this->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->get(route('admin.settings.integrations'))
        ->assertOk();
});

test('super administrators can view every settings section after confirming their password', function () {
    $user = settingsUser();
    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp]);

    foreach (SystemSettingSection::cases() as $section) {
        $this->get(route($section->routeName()))->assertOk();
    }
});

test('database driven maintenance mode protects the public website with a safe bypass', function () {
    $settings = app(SettingManager::class);
    $settings->put('maintenance', 'enabled', true);
    $settings->put('maintenance', 'message', 'Scheduled maintenance is underway.');

    $this->get(route('home'))
        ->assertStatus(503)
        ->assertSee('Scheduled maintenance is underway.');

    $superAdmin = settingsUser();

    $this->actingAs($superAdmin)
        ->get(route('home'))
        ->assertOk();
});

test('maintenance settings are not granted to ordinary administrators', function () {
    $administrator = settingsUser(RoleName::Administrator);

    $this->actingAs($administrator)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->get(route('admin.settings.maintenance'))
        ->assertForbidden();
});

test('public views consume safe database-backed settings', function () {
    $definition = collect(app(SettingRegistry::class)->forSection(SystemSettingSection::General))
        ->firstWhere('key', 'website_name');

    app(SettingManager::class)->putMany([[...$definition, 'value' => 'Configured Public Name']]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<title>Configured Public Name</title>', false);
});

test('section components enforce authorization on their server actions', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('settings.editor', ['section' => 'general'])
        ->assertForbidden();

    expect($user->can(PermissionName::SettingsGeneralUpdate))->toBeFalse();
});
