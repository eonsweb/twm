<?php

use App\Actions\Events\SaveEventType;
use App\EventScheduleType;
use App\EventStatus;
use App\Models\Event;
use App\Models\EventType;
use App\Models\PageSection;
use App\Models\User;
use App\Pages\SectionDataResolver;
use App\PermissionName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Carbon::setTestNow(Carbon::create(2026, 8, 8, 10, 0, 0, 'Africa/Accra'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('weekly schedules calculate their next occurrence without creating occurrence records', function (): void {
    $event = Event::factory()->weekly(['sunday'])->create([
        'starts_at' => Carbon::create(2026, 1, 4, 8, 0, 0, 'Africa/Accra'),
        'ends_at' => Carbon::create(2026, 1, 4, 11, 0, 0, 'Africa/Accra'),
    ]);

    expect($event->nextOccurrence()?->format('Y-m-d H:i'))->toBe('2026-08-09 08:00')
        ->and($event->scheduleLabel())->toBe('Every Sunday · 8:00 AM')
        ->and(Event::query()->count())->toBe(1);
});

test('monthly last week schedules calculate the configured weekday', function (): void {
    $event = Event::factory()->monthly('last', 'friday')->create([
        'starts_at' => Carbon::create(2026, 1, 30, 18, 0, 0, 'Africa/Accra'),
    ]);

    expect($event->nextOccurrence()?->format('Y-m-d H:i'))->toBe('2026-08-28 18:00')
        ->and($event->scheduleLabel())->toBe('Last week of every month · 6:00 PM');
});

test('yearly schedules calculate the next annual edition', function (): void {
    $event = Event::factory()->yearly(6, 1)->create([
        'starts_at' => Carbon::create(2025, 6, 1, 18, 0, 0, 'Africa/Accra'),
    ]);

    expect($event->nextOccurrence()?->format('Y-m-d H:i'))->toBe('2027-06-01 18:00')
        ->and($event->scheduleLabel())->toBe('Every June 1 · 6:00 PM');
});

test('one time events end and remain available in the past archive', function (): void {
    $event = Event::factory()->create([
        'starts_at' => now()->subDays(3),
        'ends_at' => now()->subDays(2),
        'status' => EventStatus::Archived,
        'published_at' => now()->subMonth(),
        'title' => 'Archived Anniversary Celebration',
    ]);

    expect($event->isOneTime())->toBeTrue()
        ->and($event->isPast())->toBeTrue()
        ->and($event->nextOccurrence())->toBeNull();

    $this->get(route('public.events.index', ['period' => 'past']))
        ->assertSuccessful()
        ->assertSee($event->title);
});

test('event icon fallback uses event then type then calendar', function (): void {
    $type = EventType::factory()->create(['icon' => 'users']);
    $event = Event::factory()->for($type)->create(['icon' => 'microphone']);

    expect($event->effectiveIcon())->toBe('microphone')
        ->and($event->forceFill(['icon' => null])->effectiveIcon())->toBe('users')
        ->and($event->setRelation('eventType', null)->effectiveIcon())->toBe('calendar-days');
});

test('event scopes support active published recurring and type filters', function (): void {
    $type = EventType::factory()->create(['slug' => 'weekly-service']);
    $matching = Event::factory()->for($type)->weekly()->published()->create();
    Event::factory()->for($type)->weekly()->published()->create(['is_active' => false]);
    Event::factory()->published()->create();

    expect(Event::query()->active()->published()->recurring()->ofType('weekly-service')->pluck('id')->all())
        ->toBe([$matching->id]);
});

test('homepage event sections enforce event type and limit settings', function (): void {
    $weekly = EventType::factory()->create(['slug' => 'weekly-service']);
    Event::factory()->count(5)->for($weekly)->weekly()->published()->create();
    Event::factory()->weekly()->published()->create();
    $section = PageSection::factory()->create([
        'section_type' => 'upcoming-events',
        'settings' => ['event_type_id' => $weekly->id, 'limit' => 4, 'display_style' => 'weekly-events'],
    ]);

    $data = app(SectionDataResolver::class)->resolve($section);

    expect($data['items'])->toHaveCount(4)
        ->and($data['items']->every(fn (Event $event): bool => $event->event_type_id === $weekly->id))->toBeTrue()
        ->and($data['viewAllUrl'])->toContain('type=weekly-service');
});

test('schedule aware validation requires weekly days and yearly month', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionName::EventsCreate->value);

    Livewire::actingAs($actor)
        ->test('pages::events.create')
        ->set('form.title', 'Weekly Prayer')
        ->set('form.venueName', 'Main Auditorium')
        ->set('form.scheduleType', EventScheduleType::Weekly->value)
        ->set('form.recurrenceDays', [])
        ->call('save')
        ->assertHasErrors(['form.recurrenceDays']);

    Livewire::actingAs($actor)
        ->test('pages::events.create')
        ->set('form.title', 'Annual Celebration')
        ->set('form.venueName', 'Main Auditorium')
        ->set('form.scheduleType', EventScheduleType::Yearly->value)
        ->set('form.recurrenceMonth', null)
        ->call('save')
        ->assertHasErrors(['form.recurrenceMonth']);
});

test('event types can be created updated and deactivated when in use', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo([
        PermissionName::EventTypesCreate->value,
        PermissionName::EventTypesUpdate->value,
        PermissionName::EventTypesDelete->value,
    ]);
    $action = app(SaveEventType::class);
    $type = $action->handle($actor, [
        'name' => 'Special Program', 'description' => 'Special church programmes.', 'color' => 'amber',
        'icon' => 'sparkles', 'is_active' => true, 'sort_order' => 5,
    ]);
    $action->handle($actor, [...$type->only(['name', 'description', 'color', 'icon', 'is_active', 'sort_order']), 'name' => 'Special Programme'], $type);
    Event::factory()->for($type)->create();
    $action->delete($actor, $type);

    expect($type->refresh()->name)->toBe('Special Programme')
        ->and($type->is_active)->toBeFalse()
        ->and($type->events()->exists())->toBeTrue();
});
