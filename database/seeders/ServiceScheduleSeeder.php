<?php

namespace Database\Seeders;

use App\Models\ServiceSchedule;
use Illuminate\Database\Seeder;

class ServiceScheduleSeeder extends Seeder
{
    public function run(): void
    {
        ServiceSchedule::query()
            ->where(function ($query): void {
                $query->where([
                    'name' => 'Sunday Worship',
                    'day_of_week' => 'Sunday',
                    'start_time' => '10:00',
                ])->orWhere(function ($query): void {
                    $query->where([
                        'name' => 'Midweek Bible Study',
                        'day_of_week' => 'Wednesday',
                        'start_time' => '18:30',
                    ]);
                });
            })
            ->delete();

        foreach ($this->schedules() as $displayOrder => $schedule) {
            ServiceSchedule::query()->updateOrCreate(
                ['name' => $schedule['name']],
                [...$schedule, 'display_order' => $displayOrder],
            );
        }
    }

    /** @return list<array{name: string, day_of_week: string, start_time: string, end_time: string, location: string, description: string, is_active: bool}> */
    private function schedules(): array
    {
        return [
            [
                'name' => 'Dominion Service',
                'day_of_week' => 'Sunday',
                'start_time' => '08:30',
                'end_time' => '11:00',
                'location' => 'Main Sanctuary',
                'description' => 'Our weekly Sunday worship service.',
                'is_active' => true,
            ],
            [
                'name' => 'Destiny Hour',
                'day_of_week' => 'Wednesday',
                'start_time' => '09:00',
                'end_time' => '12:00',
                'location' => 'Main Sanctuary',
                'description' => 'A weekly time of prayer, teaching, and spiritual growth.',
                'is_active' => true,
            ],
            [
                'name' => 'Worship & Prayer Service',
                'day_of_week' => 'Friday',
                'start_time' => '18:00',
                'end_time' => '20:00',
                'location' => 'Main Sanctuary',
                'description' => 'Our weekly Friday worship and prayer gathering.',
                'is_active' => true,
            ],
            [
                'name' => 'Inspiration Hour',
                'day_of_week' => 'Friday',
                'start_time' => '04:00',
                'end_time' => '05:00',
                'location' => 'Angel FM 96.1',
                'description' => 'A weekly radio programme for inspiration, prayer, and the Word.',
                'is_active' => true,
            ],
        ];
    }
}
