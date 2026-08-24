<?php

use App\Actions\Ministries\ChangeMinistryStatus;
use App\Actions\Ministries\DeleteMinistry;
use App\Actions\Ministries\SaveMinistry;
use App\MinistryStatus;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Ministry;
use App\Models\Person;
use App\Models\Sermon;
use App\Models\User;
use App\PermissionName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function validMinistryData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Youth Ministry',
        'slug' => 'youth-ministry',
        'short_description' => 'Equipping young people to follow Jesus.',
        'description' => 'A ministry for young people.',
        'mission' => 'Make disciples.',
        'vision' => 'Young people transformed by Christ.',
        'meeting_day' => 'Friday',
        'meeting_time' => '18:00',
        'meeting_location' => 'Fellowship Hall',
        'contact_email' => 'youth@example.test',
        'contact_phone' => '+233 24 123 4567',
        'display_order' => 1,
        'status' => MinistryStatus::Draft->value,
        'is_featured' => false,
        'published_at' => null,
    ], $overrides);
}

test('unauthorized users cannot access ministry administration', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('ministries.index'))
        ->assertForbidden();
});

test('users with ministry permission can access administration', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::MinistriesView->value);

    $this->actingAs($actor)->get(route('ministries.index'))->assertOk();
});

test('authorized users can create a ministry with leaders sermons and events', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::MinistriesCreate->value);
    $leader = Person::factory()->create();
    $sermon = Sermon::factory()->create();
    $event = Event::factory()->create();

    $ministry = app(SaveMinistry::class)->handle(
        $actor,
        validMinistryData(),
        [['person_id' => $leader->id, 'role_title' => 'Ministry Head', 'is_primary' => true, 'display_order' => 0]],
        [$sermon->id],
        [$event->id],
    );

    expect($ministry->leaders)->toHaveCount(1)
        ->and($ministry->sermons)->toHaveCount(1)
        ->and($event->refresh()->ministry->is($ministry))->toBeTrue()
        ->and(ActivityLog::query()->where('event', 'ministry.created')->exists())->toBeTrue();
});

test('create permission cannot publish a ministry', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::MinistriesCreate->value);

    expect(fn () => app(SaveMinistry::class)->handle(
        $actor,
        validMinistryData(['status' => MinistryStatus::Published->value, 'published_at' => now()]),
    ))->toThrow(AuthorizationException::class);
});

test('slugs are generated uniquely and manual slugs are normalized', function (): void {
    Ministry::factory()->create(['slug' => 'youth-ministry']);
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::MinistriesCreate->value);

    $ministry = app(SaveMinistry::class)->handle($actor, validMinistryData(['slug' => 'Youth Ministry']));

    expect($ministry->slug)->toBe('youth-ministry-2');
});

test('form validation rejects duplicate leaders and multiple primary leaders', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::MinistriesCreate->value);
    $leaders = Person::factory()->count(2)->create();

    Livewire::actingAs($actor)->test('pages::ministries.create')
        ->set('form.name', 'Prayer Ministry')
        ->set('form.slug', 'prayer-ministry')
        ->set('form.leaders', [
            ['person_id' => $leaders[0]->id, 'role_title' => 'Head', 'is_primary' => true, 'display_order' => 0],
            ['person_id' => $leaders[1]->id, 'role_title' => 'Assistant', 'is_primary' => true, 'display_order' => 1],
        ])
        ->call('save')
        ->assertHasErrors(['form.leaders']);
});

test('images can be uploaded replaced and removed safely', function (): void {
    Storage::fake('public');
    $actor = User::factory()->create();
    $actor->givePermissionTo([PermissionName::MinistriesCreate->value, PermissionName::MinistriesUpdate->value]);

    $ministry = app(SaveMinistry::class)->handle(
        $actor,
        validMinistryData(),
        featuredImage: UploadedFile::fake()->image('first.jpg', 1200, 630),
        logo: UploadedFile::fake()->image('logo.png', 512, 512),
    );
    $oldImage = $ministry->featured_image;
    $oldLogo = $ministry->logo;

    $ministry = app(SaveMinistry::class)->handle(
        $actor,
        validMinistryData(),
        featuredImage: UploadedFile::fake()->image('replacement.webp', 1200, 630),
        removeLogo: true,
        ministry: $ministry,
    );

    Storage::disk('public')->assertMissing([$oldImage, $oldLogo]);
    Storage::disk('public')->assertExists($ministry->featured_image);
    expect($ministry->logo)->toBeNull();
});

test('only published ministries whose publication time has arrived are public', function (): void {
    $visible = Ministry::factory()->published()->create(['name' => 'Visible Ministry']);
    $draft = Ministry::factory()->create(['name' => 'Draft Ministry']);
    $scheduled = Ministry::factory()->scheduled()->create(['name' => 'Scheduled Ministry']);
    $inactive = Ministry::factory()->inactive()->create(['name' => 'Inactive Ministry']);

    expect(Ministry::query()->published()->pluck('id')->all())->toBe([$visible->id]);
    $this->get(route('public.ministries.index'))->assertOk()
        ->assertSee($visible->name)
        ->assertDontSee($draft->name)
        ->assertDontSee($scheduled->name)
        ->assertDontSee($inactive->name);
    $this->get(route('public.ministries.show', $draft))->assertNotFound();
});

test('publishing and featuring create activity logs', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo([PermissionName::MinistriesPublish->value, PermissionName::MinistriesUpdate->value]);
    $ministry = Ministry::factory()->create();

    app(ChangeMinistryStatus::class)->publish($actor, $ministry);
    app(ChangeMinistryStatus::class)->toggleFeatured($actor, $ministry);

    expect($ministry->refresh()->status)->toBe(MinistryStatus::Published)
        ->and($ministry->published_at)->not->toBeNull()
        ->and($ministry->is_featured)->toBeTrue()
        ->and(ActivityLog::query()->where('event', 'ministry.published')->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('event', 'ministry.featured')->exists())->toBeTrue();
});

test('ministries can be deleted restored and permanently deleted without deleting related records', function (): void {
    Storage::fake('public');
    $actor = User::factory()->create();
    $actor->givePermissionTo([
        PermissionName::MinistriesDelete->value,
        PermissionName::MinistriesRestore->value,
        PermissionName::MinistriesForceDelete->value,
    ]);
    $leader = Person::factory()->create();
    $sermon = Sermon::factory()->create();
    $event = Event::factory()->create();
    $ministry = Ministry::factory()->create(['featured_image' => 'ministries/featured/test.jpg']);
    Storage::disk('public')->put($ministry->featured_image, 'image');
    $ministry->leaders()->attach($leader);
    $ministry->sermons()->attach($sermon);
    $event->update(['ministry_id' => $ministry->id]);

    app(DeleteMinistry::class)->delete($actor, $ministry);
    app(DeleteMinistry::class)->restore($actor, $ministry);
    $ministry->delete();
    app(DeleteMinistry::class)->forceDelete($actor, $ministry);

    expect(Ministry::withTrashed()->find($ministry->id))->toBeNull()
        ->and($leader->fresh())->not->toBeNull()
        ->and($sermon->fresh())->not->toBeNull()
        ->and($event->refresh()->ministry_id)->toBeNull();
    Storage::disk('public')->assertMissing('ministries/featured/test.jpg');
});

test('admin search status and featured filters return matching ministries', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::MinistriesView->value);
    $matching = Ministry::factory()->published()->featured()->create(['name' => 'Kingdom Youth Ministry']);
    $other = Ministry::factory()->create(['name' => 'Children Ministry']);

    Livewire::actingAs($actor)->test('pages::ministries.index')
        ->set('search', 'Kingdom')
        ->set('status', MinistryStatus::Published->value)
        ->set('featured', '1')
        ->assertSee($matching->name)
        ->assertDontSee($other->name);
});

test('ministry row actions have tooltips and accessible labels', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo([
        PermissionName::MinistriesView->value,
        PermissionName::MinistriesUpdate->value,
        PermissionName::MinistriesDelete->value,
        PermissionName::MinistriesRestore->value,
        PermissionName::MinistriesForceDelete->value,
        PermissionName::MinistriesPublish->value,
    ]);
    Ministry::factory()->published()->featured()->create(['name' => 'Published Featured Ministry']);
    Ministry::factory()->create(['name' => 'Draft Ministry']);
    $deleted = Ministry::factory()->create(['name' => 'Deleted Ministry']);
    $deleted->delete();

    $html = Livewire::actingAs($actor)->test('pages::ministries.index')->html();
    $labels = [
        'View ministry',
        'Edit ministry',
        'Publish ministry',
        'Unpublish ministry',
        'Mark as featured',
        'Remove from featured',
        'Delete ministry',
        'Restore ministry',
        'Permanently delete ministry',
    ];

    expect($html)->toContain('data-flux-tooltip');

    foreach ($labels as $label) {
        expect($html)
            ->toContain('aria-label="'.$label.'"')
            ->and(substr_count($html, $label))->toBeGreaterThanOrEqual(2);
    }
});

test('the icon-only remove leader control has a tooltip and accessible label', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::MinistriesCreate->value);
    $leader = Person::factory()->create();

    $html = Livewire::actingAs($actor)
        ->test('pages::ministries.create')
        ->set('form.leaders', [[
            'person_id' => $leader->id,
            'role_title' => 'Leader',
            'is_primary' => true,
            'display_order' => 0,
        ]])
        ->html();

    expect($html)
        ->toContain('data-flux-tooltip')
        ->toContain('aria-label="Remove leader"')
        ->and(substr_count($html, 'Remove leader'))->toBeGreaterThanOrEqual(2);
});

test('livewire actions authorize on the server', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::MinistriesView->value);
    $ministry = Ministry::factory()->create();

    Livewire::actingAs($actor)->test('pages::ministries.index')
        ->call('confirm', $ministry->id, 'publish')
        ->assertForbidden();
});
