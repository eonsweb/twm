<?php

namespace Database\Seeders;

use App\EventLocationType;
use App\EventStatus;
use App\Models\Event;
use App\Models\EventType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        $creatorId = User::query()->orderBy('id')->value('id');

        foreach ($this->sampleEvents() as $sample) {
            $startsAt = $sample['all_day']
                ? now()->addDays($sample['days'])->startOfDay()
                : now()->addDays($sample['days'])->setTime(18, 0);

            Event::query()->updateOrCreate(
                ['slug' => Str::slug($sample['title'])],
                [
                    'event_type_id' => EventType::query()->where('slug', $sample['type'])->value('id'),
                    'created_by' => $creatorId,
                    'updated_by' => $creatorId,
                    'title' => $sample['title'],
                    'short_description' => 'Join Triumphant World Ministry for worship, fellowship, and community.',
                    'description' => 'We warmly invite members, families, and guests to participate. Come expectant and invite someone.',
                    'location_type' => $sample['location'],
                    'venue_name' => $sample['location'] === EventLocationType::Online ? null : 'Triumphant World Ministry Auditorium',
                    'address' => $sample['location'] === EventLocationType::Online ? null : 'Accra, Ghana',
                    'city' => $sample['location'] === EventLocationType::Online ? null : 'Accra',
                    'region' => $sample['location'] === EventLocationType::Online ? null : 'Greater Accra',
                    'country' => 'Ghana',
                    'location_url' => $sample['location'] === EventLocationType::Online ? null : 'https://maps.google.com/?q=Accra',
                    'meeting_url' => $sample['location'] === EventLocationType::Physical ? null : 'https://www.youtube.com/@triumphantworldministry',
                    'starts_at' => $startsAt,
                    'ends_at' => $sample['all_day'] ? $startsAt->endOfDay() : $startsAt->addHours(3),
                    'timezone' => 'Africa/Accra',
                    'registration_required' => false,
                    'is_all_day' => $sample['all_day'],
                    'is_recurring' => false,
                    'is_featured' => $sample['featured'],
                    'is_livestreamed' => $sample['location'] !== EventLocationType::Physical,
                    'status' => $sample['status'],
                    'published_at' => $sample['status'] === EventStatus::Published ? now()->subDay() : null,
                ],
            );
        }
    }

    /**
     * @return list<array{
     *     title: string,
     *     type: string,
     *     days: int,
     *     status: EventStatus,
     *     location: EventLocationType,
     *     featured: bool,
     *     all_day: bool
     * }>
     */
    private function sampleEvents(): array
    {
        return [
            ['title' => 'Sunday Celebration Service', 'type' => 'church-service', 'days' => 7, 'status' => EventStatus::Published, 'location' => EventLocationType::Hybrid, 'featured' => true, 'all_day' => false],
            ['title' => 'Youth Prayer Encounter', 'type' => 'youth-programme', 'days' => 14, 'status' => EventStatus::Published, 'location' => EventLocationType::Physical, 'featured' => false, 'all_day' => false],
            ['title' => 'Annual Community Health Walk', 'type' => 'community-event', 'days' => 30, 'status' => EventStatus::Scheduled, 'location' => EventLocationType::Physical, 'featured' => false, 'all_day' => true],
            ['title' => 'Online Leadership Prayer Meeting', 'type' => 'leadership-meeting', 'days' => 5, 'status' => EventStatus::Draft, 'location' => EventLocationType::Online, 'featured' => false, 'all_day' => false],
            ['title' => 'Church Anniversary Awards Night', 'type' => 'anniversary', 'days' => -30, 'status' => EventStatus::Completed, 'location' => EventLocationType::Physical, 'featured' => false, 'all_day' => false],
            ['title' => 'Cancelled Fundraising Dinner', 'type' => 'fundraising', 'days' => 60, 'status' => EventStatus::Cancelled, 'location' => EventLocationType::Hybrid, 'featured' => false, 'all_day' => false],
        ];
    }
}
