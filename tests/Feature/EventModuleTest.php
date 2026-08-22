<?php

use App\Actions\Events\ChangeEventStatus;
use App\Actions\Events\DeleteEvent;
use App\Actions\Events\SaveEvent;
use App\EventLocationType;
use App\EventStatus;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Ministry;
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
    expect(Event::query()->find($event->id))->toBeNull()
        ->and(Event::withTrashed()->find($event->id)?->trashed())->toBeTrue();

    app(DeleteEvent::class)->restore($actor, Event::withTrashed()->findOrFail($event->id));
    expect($event->fresh())->not->toBeNull()
        ->and(ActivityLog::query()->where('event', 'event.restored')->exists())->toBeTrue();
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
