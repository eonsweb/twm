<?php

namespace Database\Seeders;

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
    }
}
