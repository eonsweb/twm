<?php

use App\EventLocationType;
use App\Models\Event;
use App\Models\Page;
use App\Models\PageSection;
use App\Settings\SettingManager;
use Illuminate\Support\Facades\Blade;

beforeEach(function (): void {
    app(SettingManager::class)->initializeDefaults();
});

test('event location labels retain enum behavior and tolerate missing location types', function (
    ?EventLocationType $locationType,
    ?string $venueName,
    ?string $city,
    string $expectedLabel,
): void {
    $event = (new Event)->forceFill([
        'location_type' => $locationType,
        'venue_name' => $venueName,
        'city' => $city,
    ]);

    expect($event->locationLabel())->toBe($expectedLabel);
})->with([
    'physical' => [EventLocationType::Physical, 'TWM Main Auditorium', 'Accra', 'TWM Main Auditorium'],
    'online' => [EventLocationType::Online, null, null, 'Online event'],
    'hybrid' => [EventLocationType::Hybrid, 'TWM Main Auditorium', 'Accra', 'TWM Main Auditorium + online'],
    'missing type' => [null, null, 'Accra', 'Accra'],
]);

test('an event card renders when its location type is missing', function (): void {
    $event = Event::factory()->published()->create();
    $event->forceFill([
        'location_type' => null,
        'venue_name' => 'Legacy Event Venue',
        'city' => 'Accra',
    ]);

    $html = Blade::render('<x-events.card :event="$event" />', ['event' => $event]);

    expect($html)
        ->toContain($event->title)
        ->toContain('Legacy Event Venue');
});

test('the homepage renders event cards with incomplete location attributes', function (): void {
    $page = Page::factory()->published()->create([
        'title' => 'Managed Home',
        'is_homepage' => true,
    ]);
    PageSection::factory()->for($page)->create([
        'name' => 'Upcoming events',
        'section_type' => 'upcoming-events',
        'heading' => 'Upcoming Events',
        'settings' => ['display_style' => 'cards', 'limit' => 3],
    ]);
    $event = Event::factory()->published()->create([
        'title' => 'Legacy Location Gathering',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHours(2),
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee($event->title)
        ->assertSee('Venue to be announced');
});
