<?php

namespace Database\Factories;

use App\EventLocationType;
use App\EventStatus;
use App\Models\Event;
use App\Models\EventType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Event> */
class EventFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $title = str(fake()->unique()->sentence(4))->trim('.')->title()->toString();
        $startsAt = now()->addDays(fake()->numberBetween(1, 90))->setTime(
            fake()->numberBetween(7, 18),
            fake()->randomElement([0, 30]),
        );

        return [
            'event_type_id' => EventType::factory(),
            'created_by' => User::factory(),
            'updated_by' => null,
            'title' => $title,
            'slug' => Str::slug($title),
            'short_description' => fake()->sentence(14),
            'description' => fake()->paragraphs(3, true),
            'featured_image' => null,
            'location_type' => EventLocationType::Physical,
            'venue_name' => 'Triumphant World Ministry Auditorium',
            'address' => fake()->streetAddress(),
            'city' => 'Accra',
            'region' => 'Greater Accra',
            'country' => 'Ghana',
            'location_url' => 'https://maps.google.com/?q=Accra',
            'meeting_url' => null,
            'registration_url' => null,
            'contact_name' => fake()->name(),
            'contact_phone' => '+233 '.fake()->numerify('## ### ####'),
            'contact_email' => fake()->safeEmail(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHours(2),
            'timezone' => 'Africa/Accra',
            'is_all_day' => false,
            'is_recurring' => false,
            'recurrence_rule' => null,
            'registration_required' => false,
            'registration_deadline' => null,
            'maximum_attendees' => null,
            'is_featured' => false,
            'is_livestreamed' => false,
            'status' => EventStatus::Draft,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => EventStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'status' => EventStatus::Scheduled,
            'published_at' => now()->addDay(),
        ]);
    }

    public function past(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDays(10)->addHours(2),
        ]);
    }

    public function allDay(): static
    {
        return $this->state(fn (): array => [
            'is_all_day' => true,
            'starts_at' => now()->addWeek()->startOfDay(),
            'ends_at' => now()->addWeek()->endOfDay(),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (): array => ['is_featured' => true]);
    }

    public function online(): static
    {
        return $this->state(fn (): array => [
            'location_type' => EventLocationType::Online,
            'venue_name' => null,
            'address' => null,
            'city' => null,
            'region' => null,
            'location_url' => null,
            'meeting_url' => 'https://meet.google.com/example',
        ]);
    }
}
