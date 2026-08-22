<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            RolesAndPermissionsSeeder::class,
            MediaFolderSeeder::class,
            SystemSettingSeeder::class,
            PageSeeder::class,
            ServiceScheduleSeeder::class,
            LeadershipSeeder::class,
            MinistrySeeder::class,
            PostCategorySeeder::class,
            TagSeeder::class,
            PostSeeder::class,
            EventTypeSeeder::class,
            EventSeeder::class,
            BookSeeder::class,
            PrayerRequestSeeder::class,
            ContactSubmissionSeeder::class,
            DonationSeeder::class,
        ]);
    }
}
