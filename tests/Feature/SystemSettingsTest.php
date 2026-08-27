<?php

use App\Actions\Media\ManageMedia;
use App\Mail\SettingsTestMail;
use App\MediaType;
use App\Models\Media;
use App\Models\ServiceSchedule;
use App\Models\SystemSetting;
use App\Models\User;
use App\PermissionName;
use App\RoleName;
use App\Settings\SettingManager;
use App\Settings\SettingRegistry;
use App\SettingType;
use App\SystemSettingSection;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ServiceScheduleSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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

test('branding selects persists and rerenders an existing media library image', function () {
    Storage::fake('public');
    $user = settingsUser(RoleName::MediaManager);
    $media = Media::factory()->create(['name' => 'Primary Ministry Logo']);
    Storage::disk($media->disk)->put($media->path, 'logo');

    Livewire::actingAs($user)
        ->test('settings.branding')
        ->assertDontSee('type="file"', false)
        ->assertSee('Primary logo')
        ->assertSee('Secondary logo')
        ->assertSee('Dark-mode logo')
        ->assertSee('Favicon')
        ->assertSee('Public website footer logo')
        ->assertSee('Admin dashboard logo')
        ->assertDontSee('Social sharing image')
        ->assertDontSee('Homepage hero image')
        ->assertDontSee('Organization letterhead')
        ->set('mediaIds.primary_logo', [$media->id])
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingManager::class)->get('branding', 'primary_logo'))->toBe((string) $media->id);

    Livewire::actingAs($user)
        ->test('settings.branding')
        ->assertSet('mediaIds.primary_logo', [$media->id])
        ->assertSee(Storage::disk($media->disk)->url($media->path), false);
});

test('branding colors reset to their canonical defaults without changing media state', function () {
    Storage::fake('public');
    $user = settingsUser(RoleName::MediaManager);
    $media = Media::factory()->create();
    Storage::disk($media->disk)->put($media->path, 'logo');
    $settings = app(SettingManager::class);
    $settings->put('branding', 'primary_logo', $media->id);
    $settings->put('branding', 'primary_color', '#444444');
    $defaults = collect(app(SettingRegistry::class)->forSection(SystemSettingSection::Branding))
        ->whereIn('key', ['primary_color', 'secondary_color', 'accent_color'])
        ->pluck('default', 'key');

    Livewire::actingAs($user)
        ->test('settings.branding')
        ->assertSee('Reset colors')
        ->set('values.primary_color', '#111111')
        ->set('values.secondary_color', '#222222')
        ->set('values.accent_color', '#333333')
        ->call('resetBrandColors')
        ->assertSet('values.primary_color', $defaults->get('primary_color'))
        ->assertSet('values.secondary_color', $defaults->get('secondary_color'))
        ->assertSet('values.accent_color', $defaults->get('accent_color'))
        ->assertSet('mediaIds.primary_logo', [$media->id]);

    expect($settings->get('branding', 'primary_color'))->toBe('#444444');
});

test('removing branding media clears only the reference', function () {
    Storage::fake('public');
    $user = settingsUser(RoleName::MediaManager);
    $media = Media::factory()->create();
    Storage::disk($media->disk)->put($media->path, 'logo');
    app(SettingManager::class)->put('branding', 'primary_logo', $media->id);

    Livewire::actingAs($user)
        ->test('settings.branding')
        ->call('removeMedia', 'primary_logo')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingManager::class)->get('branding', 'primary_logo'))->toBeNull();
    $this->assertModelExists($media);
    Storage::disk($media->disk)->assertExists($media->path);
});

test('branding rejects non-image and unavailable media', function () {
    Storage::fake('public');
    $user = settingsUser(RoleName::MediaManager);
    $document = Media::factory()->create([
        'media_type' => MediaType::Document,
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
    ]);
    Storage::disk($document->disk)->put($document->path, 'document');

    Livewire::actingAs($user)
        ->test('settings.branding')
        ->set('mediaIds.primary_logo', [$document->id])
        ->call('save')
        ->assertHasErrors(['mediaIds.primary_logo']);

    $missingFile = Media::factory()->create();

    Livewire::actingAs($user)
        ->test('settings.branding')
        ->set('mediaIds.primary_logo', [$missingFile->id])
        ->call('save')
        ->assertHasErrors(['mediaIds.primary_logo']);
});

test('missing branding media references render a safe placeholder', function () {
    $user = settingsUser(RoleName::MediaManager);
    app(SettingManager::class)->put('branding', 'primary_logo', 999999);

    Livewire::actingAs($user)
        ->test('settings.branding')
        ->assertSet('mediaIds.primary_logo', [999999])
        ->assertSee('Choose from Media Library');
});

test('legacy branding paths remain available until explicitly replaced or removed', function () {
    Storage::fake('public');
    $user = settingsUser(RoleName::MediaManager);
    $legacyPath = 'system-settings/branding/legacy-logo.png';
    Storage::disk('public')->put($legacyPath, 'legacy-logo');
    app(SettingManager::class)->put('branding', 'primary_logo', $legacyPath);

    Livewire::actingAs($user)
        ->test('settings.branding')
        ->assertSee(Storage::disk('public')->url($legacyPath), false)
        ->set('values.primary_color', '#701c2d')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingManager::class)->get('branding', 'primary_logo'))->toBe($legacyPath);
    Storage::disk('public')->assertExists($legacyPath);
});

test('public views resolve branding media ids through media records', function () {
    Storage::fake('public');
    $media = Media::factory()->create();
    Storage::disk($media->disk)->put($media->path, 'logo');
    app(SettingManager::class)->put('branding', 'primary_logo', $media->id);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(Storage::disk($media->disk)->url($media->path), false);
});

test('removed branding fields remain stored without controlling the homepage hero', function () {
    Storage::fake('public');
    $user = settingsUser(RoleName::MediaManager);
    $socialImage = Media::factory()->create(['path' => 'media/images/social-share.jpg']);
    $legacyHero = Media::factory()->create(['path' => 'media/images/legacy-hero.jpg']);
    $letterhead = Media::factory()->create(['path' => 'media/images/letterhead.jpg']);

    foreach ([$socialImage, $legacyHero, $letterhead] as $media) {
        Storage::disk($media->disk)->put($media->path, $media->name);
    }

    $settings = app(SettingManager::class);

    foreach ([
        'social_share_image' => [$socialImage, true],
        'homepage_hero_image' => [$legacyHero, true],
        'letterhead' => [$letterhead, false],
    ] as $key => [$media, $isPublic]) {
        SystemSetting::query()->create([
            'group' => 'branding',
            'key' => $key,
            'value' => (string) $media->id,
            'type' => SettingType::Image,
            'label' => str($key)->headline(),
            'description' => null,
            'is_public' => $isPublic,
            'is_encrypted' => false,
        ]);
    }

    $settings->forgetGroup('branding');

    Livewire::actingAs($user)
        ->test('settings.branding')
        ->set('values.primary_color', '#701c2d')
        ->call('save')
        ->assertHasNoErrors();

    expect($settings->get('branding', 'social_share_image'))->toBe((string) $socialImage->id)
        ->and($settings->get('branding', 'homepage_hero_image'))->toBe((string) $legacyHero->id)
        ->and($settings->get('branding', 'letterhead'))->toBe((string) $letterhead->id);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee(Storage::disk($socialImage->disk)->url($socialImage->path), false)
        ->assertDontSee(Storage::disk($legacyHero->disk)->url($legacyHero->path), false);
});

test('media referenced by branding cannot be deleted', function () {
    $user = settingsUser(RoleName::MediaManager);
    $media = Media::factory()->create();
    app(SettingManager::class)->put('branding', 'primary_logo', $media->id);

    expect(fn () => app(ManageMedia::class)->delete($user, $media))
        ->toThrow(ValidationException::class, 'used by Branding');

    $this->assertModelExists($media);
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
