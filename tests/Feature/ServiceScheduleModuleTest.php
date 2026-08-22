<?php

use App\EventScheduleType;
use App\EventStatus;
use App\Models\Event;
use App\Models\EventType;
use App\Models\PageSection;
use App\Models\ServiceSchedule;
use App\Models\User;
use App\Pages\SectionDataResolver;
use App\PermissionName;
use App\Settings\SettingManager;
use Database\Seeders\EventSeeder;
use Database\Seeders\EventTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ServiceScheduleSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(SettingManager::class)->initializeDefaults();
});

test('authorized administrators can view service schedules and the sidebar link', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(PermissionName::ServiceSchedulesView->value);
    ServiceSchedule::factory()->create(['name' => 'Dominion Service']);

    $this->actingAs($user)
        ->get(route('service-schedules.index'))
        ->assertOk()
        ->assertSee('Service Schedules')
        ->assertSee('Dominion Service');
});

test('authorized administrators can create a service schedule', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(PermissionName::ServiceSchedulesCreate->value);

    Livewire::actingAs($user)
        ->test('pages::service-schedules.create')
        ->set('form.name', 'Destiny Hour')
        ->set('form.dayOfWeek', 'Wednesday')
        ->set('form.startTime', '09:00')
        ->set('form.endTime', '12:00')
        ->set('form.location', 'Main Sanctuary')
        ->set('form.displayOrder', 2)
        ->call('save')
        ->assertHasNoErrors();

    $schedule = ServiceSchedule::query()->where('name', 'Destiny Hour')->firstOrFail();

    expect($schedule->day_of_week)->toBe('Wednesday')
        ->and($schedule->start_time)->toStartWith('09:00')
        ->and($schedule->end_time)->toStartWith('12:00')
        ->and($schedule->is_active)->toBeTrue();
});

test('authorized administrators can edit a service schedule', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(PermissionName::ServiceSchedulesUpdate->value);
    $schedule = ServiceSchedule::factory()->create(['name' => 'Prayer Service', 'start_time' => '18:00']);

    Livewire::actingAs($user)
        ->test('pages::service-schedules.edit', ['serviceSchedule' => $schedule])
        ->set('form.name', 'Worship & Prayer Service')
        ->set('form.endTime', '20:00')
        ->call('save')
        ->assertHasNoErrors();

    expect($schedule->refresh()->name)->toBe('Worship & Prayer Service')
        ->and($schedule->end_time)->toStartWith('20:00');
});

test('authorized administrators can activate and deactivate a service schedule', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo([
        PermissionName::ServiceSchedulesView->value,
        PermissionName::ServiceSchedulesUpdate->value,
    ]);
    $schedule = ServiceSchedule::factory()->create(['is_active' => true]);

    $component = Livewire::actingAs($user)
        ->test('pages::service-schedules.index')
        ->call('toggleActive', $schedule->id)
        ->assertHasNoErrors();

    expect($schedule->refresh()->is_active)->toBeFalse();

    $component->call('toggleActive', $schedule->id)->assertHasNoErrors();

    expect($schedule->refresh()->is_active)->toBeTrue();
});

test('authorized administrators can delete a service schedule', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo([
        PermissionName::ServiceSchedulesView->value,
        PermissionName::ServiceSchedulesDelete->value,
    ]);
    $schedule = ServiceSchedule::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::service-schedules.index')
        ->call('confirmDelete', $schedule->id)
        ->call('delete')
        ->assertHasNoErrors();

    $this->assertModelMissing($schedule);
});

test('unauthorized users cannot access service schedule administration', function (): void {
    $user = User::factory()->create();
    $schedule = ServiceSchedule::factory()->create();

    $this->actingAs($user)->get(route('service-schedules.index'))->assertForbidden();
    $this->actingAs($user)->get(route('service-schedules.create'))->assertForbidden();
    $this->actingAs($user)->get(route('service-schedules.edit', $schedule))->assertForbidden();
});

test('homepage shows all active schedules in display order and hides inactive schedules', function (): void {
    $later = ServiceSchedule::factory()->create(['name' => 'Later Schedule', 'display_order' => 20]);
    $first = ServiceSchedule::factory()->create(['name' => 'First Schedule', 'display_order' => 1]);
    ServiceSchedule::factory()->create(['name' => 'Hidden Schedule', 'display_order' => 0, 'is_active' => false]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder([$first->name, $later->name])
        ->assertDontSee('Hidden Schedule');
});

test('weekly schedules do not depend on events', function (): void {
    ServiceSchedule::factory()->create(['name' => 'Standalone Weekly Program', 'is_active' => true]);

    expect(Event::query()->count())->toBe(0);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Standalone Weekly Program');
});

test('managed homepage service sections use active ordered service schedules', function (): void {
    $later = ServiceSchedule::factory()->create(['name' => 'Managed Later Schedule', 'display_order' => 9]);
    $first = ServiceSchedule::factory()->create(['name' => 'Managed First Schedule', 'display_order' => 1]);
    ServiceSchedule::factory()->inactive()->create(['name' => 'Managed Hidden Schedule', 'display_order' => 0]);
    $section = PageSection::factory()->create(['section_type' => 'service-times']);

    $items = app(SectionDataResolver::class)->resolve($section)['items'];

    expect($items->pluck('id')->all())->toBe([$first->id, $later->id]);
});

test('service schedule seeding does not create weekly events', function (): void {
    $this->seed(ServiceScheduleSeeder::class);

    expect(ServiceSchedule::query()->ordered()->pluck('name')->all())->toBe([
        'Dominion Service',
        'Destiny Hour',
        'Worship & Prayer Service',
        'Inspiration Hour',
    ])->and(Event::query()->count())->toBe(0);
});

test('event seeder removes known weekly duplicates without removing legitimate dated events', function (): void {
    $this->seed(EventTypeSeeder::class);
    $weeklyType = EventType::query()->create([
        'name' => 'Weekly Service',
        'slug' => 'weekly-service',
        'is_active' => true,
        'sort_order' => 100,
    ]);
    $legacyWeekly = Event::factory()->for($weeklyType)->weekly()->create([
        'title' => 'Sunday Dominion Service',
        'slug' => 'sunday-dominion-service',
    ]);
    $existingWeekly = Event::factory()->for($weeklyType)->weekly()->create([
        'title' => 'Joshua Generation',
        'slug' => 'joshua-generation',
        'short_description' => 'Youth intercessor group',
        'venue_name' => 'Church Auditorium',
        'recurrence_days' => ['tuesday'],
        'status' => EventStatus::Draft,
    ]);
    $legitimateEvent = Event::factory()->create([
        'title' => 'Building Dedication',
        'slug' => 'building-dedication',
        'schedule_type' => EventScheduleType::OneTime,
        'is_recurring' => false,
    ]);

    $this->seed(EventSeeder::class);

    expect(Event::query()->find($legacyWeekly->id))->toBeNull()
        ->and(Event::withTrashed()->find($legacyWeekly->id)?->trashed())->toBeTrue()
        ->and(Event::query()->find($existingWeekly->id))->toBeNull()
        ->and(ServiceSchedule::query()->where([
            'name' => 'Joshua Generation',
            'day_of_week' => 'Tuesday',
            'location' => 'Church Auditorium',
            'is_active' => false,
        ])->exists())->toBeTrue()
        ->and($legitimateEvent->fresh())->not->toBeNull()
        ->and(Event::query()->where('slug', '20th-anniversary-celebration')->exists())->toBeTrue()
        ->and(EventType::query()->where('slug', 'weekly-service')->exists())->toBeFalse();
});

test('event type seeding only removes unreferenced timetable categories', function (): void {
    $referencedType = EventType::query()->create([
        'name' => 'Weekly Service',
        'slug' => 'weekly-service',
        'is_active' => true,
        'sort_order' => 100,
    ]);
    $event = Event::factory()->for($referencedType)->create();
    EventType::query()->create(['name' => 'Church Service', 'slug' => 'church-service', 'is_active' => true]);

    $this->seed(EventTypeSeeder::class);

    expect($referencedType->fresh())->not->toBeNull()
        ->and($event->fresh())->not->toBeNull()
        ->and(EventType::query()->where('slug', 'church-service')->exists())->toBeFalse();
});
