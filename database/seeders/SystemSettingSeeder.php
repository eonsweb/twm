<?php

namespace Database\Seeders;

use App\Models\ServiceSchedule;
use App\Settings\SettingManager;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(SettingManager $settings): void
    {
        $settings->initializeDefaults();

        ServiceSchedule::query()->firstOrCreate(
            ['name' => 'Sunday Worship', 'day_of_week' => 'Sunday', 'start_time' => '10:00'],
            ['location' => 'Main sanctuary', 'display_order' => 0, 'is_active' => true],
        );

        ServiceSchedule::query()->firstOrCreate(
            ['name' => 'Midweek Bible Study', 'day_of_week' => 'Wednesday', 'start_time' => '18:30'],
            ['location' => 'Main sanctuary', 'display_order' => 1, 'is_active' => true],
        );
    }
}
