<?php

namespace Database\Seeders;

use App\Models\EventType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EventTypeSeeder extends Seeder
{
    private const LEGACY_TIMETABLE_TYPES = [
        'weekly-service',
        'church-service',
        'prayer-meeting',
    ];

    public function run(): void
    {
        $types = [
            ['Prayer Program', 'violet', 'heart'],
            ['Encounter', 'amber', 'sparkles'],
            ['Revival', 'rose', 'sparkles'],
            ['Conference', 'blue', 'user-group'],
            ['Outreach', 'cyan', 'megaphone'],
            ['Crusade', 'rose', 'megaphone'],
            ['Anniversary', 'amber', 'sparkles'],
            ['Dedication', 'teal', 'gift'],
            ['Seasonal Celebration', 'yellow', 'sun'],
            ['Thanksgiving', 'emerald', 'gift'],
            ['Special Program', 'indigo', 'star'],
            ['Youth Programme', 'indigo', 'users'],
            ['Leadership Meeting', 'slate', 'briefcase'],
            ['Fundraising', 'yellow', 'banknotes'],
            ['Community Event', 'teal', 'globe-alt'],
            ['Other', 'zinc', 'calendar-days'],
        ];

        foreach ($types as $sortOrder => [$name, $color, $icon]) {
            EventType::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => "Events categorized as {$name}.",
                    'color' => $color,
                    'icon' => $icon,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ],
            );
        }

        self::removeUnreferencedTimetableTypes();
    }

    public static function removeUnreferencedTimetableTypes(): void
    {
        EventType::query()
            ->whereIn('slug', self::LEGACY_TIMETABLE_TYPES)
            ->whereDoesntHave('events', fn ($query) => $query->withTrashed())
            ->delete();
    }
}
