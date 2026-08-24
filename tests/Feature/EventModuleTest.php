<?php

use App\Actions\Events\ChangeEventStatus;
use App\Actions\Events\DeleteEvent;
use App\Actions\Events\SaveEvent;
use App\EventLocationType;
use App\EventStatus;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Media;
use App\Models\Ministry;
use App\Models\User;
use App\PermissionName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function validEventData(EventType $eventType, array $overrides = []): array
{
    return array_merge([
        'event_type_id' => $eventType->id,
        'title' => 'Kingdom Advancement Conference',
        'short_description' => 'A conference for the whole church.',
        'description' => 'Join us for worship, teaching, and fellowship.',
        'location_type' => EventLocationType::Physical->value,
        'venue_name' => 'TWM Main Auditorium',
        'address' => 'Accra, Ghana',
        'city' => 'Accra',
        'region' => 'Greater Accra',
        'country' => 'Ghana',
        'location_url' => 'https://maps.google.com/?q=Accra',
        'meeting_url' => null,
        'registration_url' => null,
        'contact_name' => 'Church Office',
        'contact_phone' => '+233 20 000 0000',
        'contact_email' => 'events@example.com',
        'starts_at' => now()->addWeek(),
        'ends_at' => now()->addWeek()->addHours(3),
        'timezone' => 'Africa/Accra',
        'is_all_day' => false,
        'is_recurring' => false,
        'recurrence_rule' => null,
        'registration_required' => false,
        'registration_deadline' => null,
        'maximum_attendees' => null,
        'is_featured' => false,
        'is_livestreamed' => false,
        'status' => EventStatus::Draft->value,
        'published_at' => null,
    ], $overrides);
}

test('authorized users can view event administration', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(PermissionName::EventsView->value);

    $this->actingAs($user)->get(route('events.index'))->assertOk();
});

test('authorized users can open the event creation page', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(PermissionName::EventsCreate->value);

    $this->actingAs($user)
        ->get(route('events.create'))
        ->assertOk()
        ->assertSee('Create event');
});

test('authorized users can open known event edit routes', function (string $slug): void {
    $user = User::factory()->create();
    $user->givePermissionTo(PermissionName::EventsUpdate->value);
    $eventType = EventType::factory()->create(['name' => 'Active Event Type']);
    $event = Event::factory()->for($eventType)->create(['slug' => $slug]);

    $this->actingAs($user)
        ->get(route('events.edit', $event))
        ->assertOk()
        ->assertSee($event->title)
        ->assertSee($eventType->name);
})->with([
    'joshua generation' => 'joshua-generation',
    'sunday dominion service' => 'sunday-dominion-service',
    'hour of warriors' => 'hour-of-warriors',
]);

test('event edit selectors retain inactive current assignments', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(PermissionName::EventsUpdate->value);
    $currentEventType = EventType::factory()->create(['name' => 'Current Inactive Type', 'is_active' => false]);
    $otherInactiveEventType = EventType::factory()->create(['name' => 'Other Inactive Type', 'is_active' => false]);
    $currentMinistry = Ministry::factory()->inactive()->create(['name' => 'Current Inactive Ministry']);
    $otherInactiveMinistry = Ministry::factory()->inactive()->create(['name' => 'Other Inactive Ministry']);
    $event = Event::factory()->for($currentEventType)->create(['ministry_id' => $currentMinistry->id]);

    $this->actingAs($user)
        ->get(route('events.edit', $event))
        ->assertOk()
        ->assertSee($currentEventType->name)
        ->assertDontSee($otherInactiveEventType->name)
        ->assertSee($currentMinistry->name)
        ->assertDontSee($otherInactiveMinistry->name);
});

test('an event media library selection is persisted when creating an event', function (): void {
    Storage::fake('public');
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::EventsCreate->value);
    $media = Media::factory()->create();
    Storage::disk($media->disk)->put($media->path, 'event artwork');

    Livewire::actingAs($actor)
        ->test('pages::events.create')
        ->set('form.title', 'Media Library Event')
        ->set('form.venueName', 'Main Auditorium')
        ->set('form.featuredImageIds', [$media->id])
        ->call('save')
        ->assertHasNoErrors();

    $event = Event::query()->where('title', 'Media Library Event')->firstOrFail();

    expect($event->featured_image_id)->toBe($media->id)
        ->and($event->featuredImage->is($media))->toBeTrue();
});

test('the event edit form hydrates its existing media library image', function (): void {
    Storage::fake('public');
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::EventsUpdate->value);
    $media = Media::factory()->create(['name' => 'Existing Event Artwork']);
    Storage::disk($media->disk)->put($media->path, 'event artwork');
    $event = Event::factory()->create(['featured_image_id' => $media->id]);

    Livewire::actingAs($actor)
        ->test('pages::events.edit', ['event' => $event])
        ->assertSet('form.featuredImageIds', [$media->id])
        ->assertSee($media->name);
});

test('the update event control remains inside the event form after browser html parsing', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo([
        PermissionName::EventsUpdate->value,
        PermissionName::MediaCreate->value,
    ]);
    $event = Event::factory()->create();

    $html = $this->actingAs($actor)
        ->get(route('events.edit', $event))
        ->assertOk()
        ->getContent();
    $document = new DOMDocument;
    $previousLibxmlState = libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previousLibxmlState);
    $xpath = new DOMXPath($document);
    $updateButtons = $xpath->query('//button[contains(normalize-space(.), "Update event")]');
    $submitButtons = $xpath->query('//button[contains(normalize-space(.), "Update event") and @type="submit"]');
    $eventForms = $xpath->query('//button[contains(normalize-space(.), "Update event")]/ancestor::form[1]');
    $eventFormContents = Str::of($html)
        ->after('<form wire:submit="save" class="space-y-6" data-event-edit-form>')
        ->before('</form>');

    expect($updateButtons)->not->toBeFalse()
        ->and($updateButtons?->length)->toBe(1)
        ->and($submitButtons)->not->toBeFalse()
        ->and($submitButtons?->length)->toBe(1)
        ->and($eventForms)->not->toBeFalse()
        ->and($eventForms?->length)->toBe(1)
        ->and($eventFormContents->contains('<form'))->toBeFalse()
        ->and($eventFormContents->contains('Update event'))->toBeTrue()
        ->and($eventFormContents->contains('Updating...'))->toBeTrue();
});

test('editing event details without changing the image preserves its media association', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::EventsUpdate->value);
    $media = Media::factory()->create();
    $event = Event::factory()->create(['featured_image_id' => $media->id]);

    Livewire::actingAs($actor)
        ->test('pages::events.edit', ['event' => $event])
        ->set('form.title', 'Updated Event Title')
        ->set('form.description', 'Updated event description.')
        ->call('save')
        ->assertHasNoErrors();

    expect($event->refresh())
        ->title->toBe('Updated Event Title')
        ->description->toBe('Updated event description.')
        ->featured_image_id->toBe($media->id);
});

test('invalid event edits show validation errors and leave the event unchanged', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::EventsUpdate->value);
    $event = Event::factory()->create();
    $originalTitle = $event->title;

    Livewire::actingAs($actor)
        ->test('pages::events.edit', ['event' => $event])
        ->set('form.title', '')
        ->call('save')
        ->assertHasErrors(['form.title' => 'required'])
        ->assertSee('Event could not be updated')
        ->assertSee('Please correct the highlighted fields.');

    expect($event->refresh()->title)->toBe($originalTitle);
});

test('an event image can be replaced with another media library image', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::EventsUpdate->value);
    $oldMedia = Media::factory()->create();
    $newMedia = Media::factory()->create();
    $event = Event::factory()->create(['featured_image_id' => $oldMedia->id]);

    Livewire::actingAs($actor)
        ->test('pages::events.edit', ['event' => $event])
        ->set('form.featuredImageIds', [$newMedia->id])
        ->call('save')
        ->assertHasNoErrors();

    expect($event->refresh()->featured_image_id)->toBe($newMedia->id)
        ->and($oldMedia->fresh())->not->toBeNull();
});

test('an event image is removed only after its media selection is explicitly cleared', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::EventsUpdate->value);
    $media = Media::factory()->create();
    $event = Event::factory()->create(['featured_image_id' => $media->id]);

    Livewire::actingAs($actor)
        ->test('pages::events.edit', ['event' => $event])
        ->call('removeEventImage')
        ->assertSet('form.featuredImageIds', [])
        ->assertSet('form.removeFeaturedImage', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($event->refresh()->featured_image_id)->toBeNull()
        ->and($media->fresh())->not->toBeNull();
});

test('explicitly removing a legacy event upload deletes its owned file', function (): void {
    Storage::fake('public');
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::EventsUpdate->value);
    $path = 'events/legacy-event-artwork.jpg';
    Storage::disk('public')->put($path, 'legacy event artwork');
    $event = Event::factory()->create(['featured_image' => $path]);

    Livewire::actingAs($actor)
        ->test('pages::events.edit', ['event' => $event])
        ->call('removeEventImage')
        ->call('save')
        ->assertHasNoErrors();

    expect($event->refresh()->featured_image)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('unauthorized users cannot access event administration', function (): void {
    $this->actingAs(User::factory()->create())->get(route('events.index'))->assertForbidden();
});

test('authorized users can create events and activity is recorded', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::EventsCreate->value);
    $type = EventType::factory()->create();

    $event = app(SaveEvent::class)->handle($actor, validEventData($type));

    expect($event->title)->toBe('Kingdom Advancement Conference')
        ->and($event->creator->is($actor))->toBeTrue()
        ->and($event->eventType->is($type))->toBeTrue()
        ->and(ActivityLog::query()->where('event', 'event.created')->exists())->toBeTrue();
});

test('required event fields are validated', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::EventsCreate->value);

    Livewire::actingAs($actor)
        ->test('pages::events.create')
        ->set('form.title', '')
        ->set('form.startDate', '')
        ->set('form.venueName', '')
        ->call('save')
        ->assertHasErrors(['form.title', 'form.startDate', 'form.venueName']);
});

test('event forms default to physical and reject a missing location type', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::EventsCreate->value);

    Livewire::actingAs($actor)
        ->test('pages::events.create')
        ->assertSet('form.locationType', EventLocationType::Physical->value)
        ->set('form.locationType', null)
        ->call('save')
        ->assertHasErrors(['form.locationType']);
});

test('an event end cannot precede its start', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::EventsCreate->value);

    Livewire::actingAs($actor)
        ->test('pages::events.create')
        ->set('form.title', 'Prayer Night')
        ->set('form.venueName', 'Main Auditorium')
        ->set('form.startDate', now()->addWeek()->toDateString())
        ->set('form.startTime', '20:00')
        ->set('form.endDate', now()->addWeek()->toDateString())
        ->set('form.endTime', '18:00')
        ->call('save')
        ->assertHasErrors(['form.endTime']);
});

test('a registration deadline cannot be after the event start', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::EventsCreate->value);
    $start = now()->addWeek();

    Livewire::actingAs($actor)
        ->test('pages::events.create')
        ->set('form.title', 'Registered Gathering')
        ->set('form.venueName', 'Main Auditorium')
        ->set('form.startDate', $start->toDateString())
        ->set('form.startTime', $start->format('H:i'))
        ->set('form.registrationRequired', true)
        ->set('form.registrationUrl', 'https://example.com/register')
        ->set('form.registrationDeadline', $start->addHour()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasErrors(['form.registrationDeadline']);
});

test('event slugs are generated and remain unique across soft deletes', function (): void {
    $type = EventType::factory()->create();
    $first = Event::factory()->for($type)->create(['title' => 'Annual Revival', 'slug' => '']);
    $first->delete();
    $second = Event::factory()->for($type)->create(['title' => 'Annual Revival', 'slug' => '']);

    expect($first->slug)->toBe('annual-revival')
        ->and($second->slug)->toBe('annual-revival-2');
});

test('events can be updated without changing their stable slug', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::EventsUpdate->value);
    $event = Event::factory()->create();
    $slug = $event->slug;

    app(SaveEvent::class)->handle(
        $actor,
        validEventData($event->eventType, ['title' => 'Updated Conference']),
        event: $event,
    );

    expect($event->refresh()->title)->toBe('Updated Conference')
        ->and($event->slug)->toBe($slug)
        ->and($event->updater->is($actor))->toBeTrue();
});

test('publishing and cancelling require their dedicated permissions', function (): void {
    $actor = User::factory()->create();
    $event = Event::factory()->create();

    expect(fn () => app(ChangeEventStatus::class)->publish($actor, $event))
        ->toThrow(AuthorizationException::class);

    $actor->givePermissionTo([PermissionName::EventsPublish->value, PermissionName::EventsCancel->value]);
    app(ChangeEventStatus::class)->publish($actor, $event);
    expect($event->refresh()->status)->toBe(EventStatus::Published)
        ->and($event->published_at)->not->toBeNull();

    app(ChangeEventStatus::class)->cancel($actor, $event);
    expect($event->refresh()->status)->toBe(EventStatus::Cancelled)
        ->and(ActivityLog::query()->where('event', 'event.cancelled')->exists())->toBeTrue();
});

test('update permission cannot be used to unpublish an event', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::EventsUpdate->value);
    $event = Event::factory()->published()->create();

    expect(fn () => app(SaveEvent::class)->handle(
        $actor,
        validEventData($event->eventType, ['status' => EventStatus::Draft->value]),
        event: $event,
    ))->toThrow(AuthorizationException::class);

    expect($event->refresh()->status)->toBe(EventStatus::Published);
});

test('events can be soft deleted and restored with permission', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo([PermissionName::EventsDelete->value, PermissionName::EventsRestore->value]);
    $event = Event::factory()->create();

    app(DeleteEvent::class)->delete($actor, $event);
    $this->assertSoftDeleted($event);
    expect(Event::query()->find($event->id))->toBeNull()
        ->and(Event::withTrashed()->find($event->id))->not->toBeNull()
        ->and(Event::withTrashed()->find($event->id)?->trashed())->toBeTrue();

    app(DeleteEvent::class)->restore($actor, Event::withTrashed()->findOrFail($event->id));
    expect($event->fresh())->not->toBeNull()
        ->and(ActivityLog::query()->where('event', 'event.restored')->exists())->toBeTrue();
});

test('authorized users can permanently delete only trashed events', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::EventsForceDelete->value);
    $event = Event::factory()->create();
    $event->delete();

    app(DeleteEvent::class)->forceDelete($actor, $event);

    expect(Event::withTrashed()->find($event->id))->toBeNull()
        ->and(ActivityLog::query()->where('event', 'event.force-deleted')->exists())->toBeTrue();
});

test('permanent deletion cannot target an active event', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::EventsForceDelete->value);
    $event = Event::factory()->create();

    expect(fn () => app(DeleteEvent::class)->forceDelete($actor, $event))
        ->toThrow(ModelNotFoundException::class);

    expect($event->fresh())->not->toBeNull();
});

test('unauthorized users cannot permanently delete an event', function (): void {
    $actor = User::factory()->create();
    $event = Event::factory()->create();
    $event->delete();

    expect(fn () => app(DeleteEvent::class)->forceDelete($actor, $event))
        ->toThrow(AuthorizationException::class);

    expect(Event::onlyTrashed()->find($event->id))->not->toBeNull();
});

test('permanent deletion removes owned uploads but preserves shared media library assets', function (): void {
    Storage::fake('public');
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::EventsForceDelete->value);
    $sharedMedia = Media::factory()->create();
    $ownedImage = 'events/owned-event-artwork.jpg';
    Storage::disk('public')->put($sharedMedia->path, 'shared artwork');
    Storage::disk('public')->put($ownedImage, 'owned artwork');
    $event = Event::factory()->create([
        'featured_image' => $ownedImage,
        'featured_image_id' => $sharedMedia->id,
    ]);
    $event->delete();

    app(DeleteEvent::class)->forceDelete($actor, $event);

    expect(Event::withTrashed()->find($event->id))->toBeNull()
        ->and($sharedMedia->fresh())->not->toBeNull();
    Storage::disk('public')->assertMissing($ownedImage);
    Storage::disk('public')->assertExists($sharedMedia->path);
});

test('the trashed events listing confirms and completes permanent deletion', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo([
        PermissionName::EventsView->value,
        PermissionName::EventsRestore->value,
        PermissionName::EventsForceDelete->value,
    ]);
    $event = Event::factory()->create(['title' => 'Event Awaiting Permanent Deletion']);
    $event->delete();

    Livewire::actingAs($actor)
        ->test('pages::events.index')
        ->set('status', 'deleted')
        ->assertSee($event->title)
        ->assertSee('Delete Permanently')
        ->call('confirm', $event->id, 'force-delete')
        ->assertSet('showConfirmModal', true)
        ->assertSee('Permanently delete this event?')
        ->assertSee('This action cannot be undone.')
        ->call('executeConfirmed')
        ->assertSet('showConfirmModal', false)
        ->assertDontSee($event->title);

    expect(Event::withTrashed()->find($event->id))->toBeNull();
});

test('the permanent deletion handler safely rejects active and stale event ids', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo([
        PermissionName::EventsView->value,
        PermissionName::EventsForceDelete->value,
    ]);
    $activeEvent = Event::factory()->create();

    Livewire::actingAs($actor)
        ->test('pages::events.index')
        ->call('confirm', $activeEvent->id, 'force-delete')
        ->assertSet('showConfirmModal', false)
        ->call('confirm', PHP_INT_MAX, 'force-delete')
        ->assertSet('showConfirmModal', false);

    expect($activeEvent->fresh())->not->toBeNull();
});

test('a stale permanent deletion confirmation is handled after another administrator restores the event', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo([
        PermissionName::EventsView->value,
        PermissionName::EventsForceDelete->value,
    ]);
    $event = Event::factory()->create();
    $event->delete();
    $component = Livewire::actingAs($actor)
        ->test('pages::events.index')
        ->call('confirm', $event->id, 'force-delete')
        ->assertSet('showConfirmModal', true);

    $event->restore();

    $component->call('executeConfirmed')
        ->assertSet('showConfirmModal', false);

    expect($event->fresh())->not->toBeNull();
});

test('admin search and filters return matching events', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::EventsView->value);
    $conference = EventType::factory()->create(['name' => 'Conference']);
    $service = EventType::factory()->create(['name' => 'Service']);
    $matching = Event::factory()->for($conference)->published()->create(['title' => 'Kingdom Builders Summit']);
    $other = Event::factory()->for($service)->create(['title' => 'Midweek Service']);

    Livewire::actingAs($actor)
        ->test('pages::events.index')
        ->set('search', 'Builders')
        ->set('eventType', (string) $conference->id)
        ->set('status', EventStatus::Published->value)
        ->assertSee($matching->title)
        ->assertDontSee($other->title);
});

test('only publicly available events appear on public pages', function (): void {
    $published = Event::factory()->published()->create(['title' => 'Public Worship Night']);
    $draft = Event::factory()->create(['title' => 'Private Planning Meeting']);
    $scheduled = Event::factory()->scheduled()->create(['title' => 'Scheduled Announcement']);
    $deleted = Event::factory()->published()->create(['title' => 'Deleted Public Event']);
    $deleted->delete();

    $this->get(route('public.events.index'))
        ->assertOk()
        ->assertSee($published->title)
        ->assertDontSee($draft->title)
        ->assertDontSee($scheduled->title)
        ->assertDontSee($deleted->title);

    $this->get(route('public.events.show', $published))->assertOk()->assertSee($published->title);
    $this->get(route('public.events.show', $draft))->assertNotFound();
    $this->get(route('public.events.show', $scheduled))->assertNotFound();
});

test('the public event detail page prioritizes a usable media library image', function (): void {
    Storage::fake('public');
    $media = Media::factory()->create();
    Storage::disk($media->disk)->put($media->path, 'event artwork');
    $event = Event::factory()->published()->create(['featured_image_id' => $media->id]);

    $this->get(route('public.events.show', $event))
        ->assertOk()
        ->assertSee('data-event-image', false)
        ->assertSee($media->publicImageUrl(), false)
        ->assertDontSee('data-event-image-fallback', false);
});

test('the public event detail page falls back safely when its media file is missing', function (): void {
    Storage::fake('public');
    $media = Media::factory()->create();
    $event = Event::factory()->published()->create(['featured_image_id' => $media->id]);

    $this->get(route('public.events.show', $event))
        ->assertOk()
        ->assertSee('data-event-image-fallback', false)
        ->assertDontSee('data-event-image src=', false);
});

test('cancelled previously published events retain a public cancellation notice', function (): void {
    $event = Event::factory()->create([
        'status' => EventStatus::Cancelled,
        'published_at' => now()->subDay(),
    ]);

    $this->get(route('public.events.show', $event))
        ->assertOk()
        ->assertSee('This event has been cancelled');
});

test('upcoming and past scopes handle null end times', function (): void {
    $upcoming = Event::factory()->create(['starts_at' => now()->addDay(), 'ends_at' => null]);
    $ongoing = Event::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
    $past = Event::factory()->create(['starts_at' => now()->subDay(), 'ends_at' => null]);

    expect(Event::query()->upcoming()->pluck('id')->all())
        ->toContain($upcoming->id, $ongoing->id)
        ->not->toContain($past->id)
        ->and(Event::query()->past()->pluck('id')->all())
        ->toContain($past->id)
        ->not->toContain($upcoming->id);
});

test('event type relationships work in both directions', function (): void {
    $type = EventType::factory()->create();
    $events = Event::factory()->count(2)->for($type)->create();

    expect($type->events)->toHaveCount(2)
        ->and($events->first()->eventType->is($type))->toBeTrue();
});

test('event image validation rejects non images', function (): void {
    Storage::fake('public');
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::EventsCreate->value);

    Livewire::actingAs($actor)
        ->test('pages::events.create')
        ->set('form.title', 'Image Validation Event')
        ->set('form.venueName', 'Main Auditorium')
        ->set('form.featuredImage', UploadedFile::fake()->create('agenda.pdf', 100, 'application/pdf'))
        ->call('save')
        ->assertHasErrors(['form.featuredImage']);
});

test('valid event images are stored on the public disk', function (): void {
    Storage::fake('public');
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::EventsCreate->value);
    $type = EventType::factory()->create();

    $event = app(SaveEvent::class)->handle(
        $actor,
        validEventData($type),
        UploadedFile::fake()->image('conference.jpg', 1200, 630),
    );

    expect($event->featured_image)->not->toBeNull();
    Storage::disk('public')->assertExists($event->featured_image);
});

test('livewire state actions enforce permissions server side', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::EventsView->value);
    $event = Event::factory()->create();

    Livewire::actingAs($actor)
        ->test('pages::events.index')
        ->call('confirm', $event->id, 'publish')
        ->assertForbidden();
});
