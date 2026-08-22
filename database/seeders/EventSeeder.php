<?php

namespace Database\Seeders;

use App\EventLocationType;
use App\EventScheduleType;
use App\EventStatus;
use App\Models\Event;
use App\Models\EventType;
use App\Models\ServiceSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EventSeeder extends Seeder
{
    private const LEGACY_WEEKLY_SLUGS = [
        'sunday-dominion-service',
        'tuesday-prayer-meeting',
        'wednesday-miracle-service',
        'friday-prophetic-meeting',
    ];

    public function run(): void
    {
        $creatorId = User::query()->oldest('id')->value('id');

        $legacyWeeklyEvents = Event::query()
            ->whereIn('slug', self::LEGACY_WEEKLY_SLUGS)
            ->where('schedule_type', EventScheduleType::Weekly);

        $legacyWeeklyEvents->update(['event_type_id' => null]);
        $legacyWeeklyEvents->delete();

        $this->migrateRemainingWeeklyEvents();

        foreach ($this->sampleEvents() as $sortOrder => $sample) {
            Event::withTrashed()->firstOrCreate(
                ['slug' => Str::slug($sample['title'])],
                [
                    'event_type_id' => EventType::query()->where('slug', $sample['type'])->value('id'),
                    'created_by' => $creatorId,
                    'updated_by' => $creatorId,
                    'title' => $sample['title'],
                    'short_description' => $sample['summary'],
                    'description' => 'We warmly invite members, families, and guests to participate. Come expectant and invite someone.',
                    'icon' => $sample['icon'],
                    'location_type' => EventLocationType::Physical,
                    'venue_name' => 'Triumphant World Ministry Auditorium',
                    'address' => 'Accra, Ghana',
                    'city' => 'Accra',
                    'region' => 'Greater Accra',
                    'country' => 'Ghana',
                    'starts_at' => $sample['starts_at'],
                    'ends_at' => $sample['ends_at'],
                    'timezone' => 'Africa/Accra',
                    'schedule_type' => $sample['schedule_type'],
                    'is_recurring' => $sample['schedule_type']->isRecurring(),
                    'recurrence_rule' => $sample['schedule_type']->value,
                    'recurrence_interval' => 1,
                    'recurrence_days' => $sample['days'],
                    'recurrence_week_of_month' => $sample['week'],
                    'recurrence_month' => $sample['month'],
                    'recurrence_day_of_month' => $sample['day_of_month'],
                    'registration_required' => false,
                    'is_all_day' => false,
                    'is_featured' => $sample['featured'],
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                    'is_livestreamed' => false,
                    'status' => EventStatus::Published,
                    'published_at' => now()->subDay(),
                ],
            );
        }

        EventTypeSeeder::removeUnreferencedTimetableTypes();
    }

    private function migrateRemainingWeeklyEvents(): void
    {
        $nextDisplayOrder = ((int) ServiceSchedule::query()->max('display_order')) + 1;

        Event::query()
            ->where('schedule_type', EventScheduleType::Weekly)
            ->get()
            ->each(function (Event $event) use (&$nextDisplayOrder): void {
                $timezone = $event->timezone ?: 'Africa/Accra';
                $days = collect($event->recurrence_days)
                    ->filter()
                    ->map(fn (string $day): string => Str::title($day))
                    ->values();

                if ($days->isEmpty()) {
                    $days->push($event->starts_at->setTimezone($timezone)->format('l'));
                }

                foreach ($days as $day) {
                    $name = $days->count() > 1 ? "{$event->title} ({$day})" : $event->title;

                    ServiceSchedule::query()->updateOrCreate(
                        ['name' => $name, 'day_of_week' => $day],
                        [
                            'start_time' => $event->starts_at->setTimezone($timezone)->format('H:i'),
                            'end_time' => $event->ends_at?->setTimezone($timezone)->format('H:i'),
                            'location' => $event->venue_name ?: $event->address,
                            'description' => $event->short_description ?: $event->description,
                            'display_order' => $nextDisplayOrder++,
                            'is_active' => $event->is_active && $event->status === EventStatus::Published,
                        ],
                    );
                }

                $event->forceFill(['event_type_id' => null])->save();
                $event->delete();
            });
    }

    /** @return list<array<string, mixed>> */
    private function sampleEvents(): array
    {
        $encounterStart = now('Africa/Accra')->next(CarbonImmutable::FRIDAY)->setTime(18, 0);
        $anniversaryStart = CarbonImmutable::create(2026, 10, 1, 18, 0, 0, 'Africa/Accra');

        return [
            [
                'title' => '3 Days Encounter', 'type' => 'encounter', 'summary' => 'A monthly three-day encounter programme.',
                'icon' => 'sparkles', 'starts_at' => $encounterStart, 'ends_at' => $encounterStart->addHours(3),
                'schedule_type' => EventScheduleType::Monthly, 'days' => ['friday'], 'week' => 'last', 'month' => null,
                'day_of_month' => null, 'featured' => false,
            ],
            [
                'title' => 'Pentecostal Month', 'type' => 'seasonal-celebration', 'summary' => 'Our annual June season of Pentecostal teaching and worship.',
                'icon' => 'sun', 'starts_at' => CarbonImmutable::create(2026, 6, 1, 18, 0, 0, 'Africa/Accra'), 'ends_at' => CarbonImmutable::create(2026, 6, 1, 21, 0, 0, 'Africa/Accra'),
                'schedule_type' => EventScheduleType::Yearly, 'days' => null, 'week' => null, 'month' => 6,
                'day_of_month' => 1, 'featured' => false,
            ],
            [
                'title' => '20th Anniversary Celebration', 'type' => 'anniversary', 'summary' => 'Celebrating twenty years of God’s faithfulness, climaxing on 10 October 2026.',
                'icon' => 'gift', 'starts_at' => $anniversaryStart, 'ends_at' => CarbonImmutable::create(2026, 10, 10, 21, 0, 0, 'Africa/Accra'),
                'schedule_type' => EventScheduleType::OneTime, 'days' => null, 'week' => null, 'month' => null,
                'day_of_month' => null, 'featured' => true,
            ],
        ];
    }
}
