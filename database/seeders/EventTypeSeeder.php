<?php

namespace Database\Seeders;

use App\Models\EventType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EventTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['Church Service', 'emerald', 'building-library'],
            ['Conference', 'blue', 'user-group'],
            ['Revival', 'rose', 'fire'],
            ['Prayer Meeting', 'violet', 'heart'],
            ['Anniversary', 'amber', 'sparkles'],
            ['Outreach', 'cyan', 'megaphone'],
            ['Youth Programme', 'indigo', 'users'],
            ['Leadership Meeting', 'slate', 'briefcase'],
            ['Fundraising', 'yellow', 'banknotes'],
            ['Community Event', 'teal', 'globe-alt'],
            ['Other', 'zinc', 'calendar-days'],
        ];

        foreach ($types as $sortOrder => [$name, $color, $icon]) {
            EventType::query()->updateOrCreate(
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
    }
}
