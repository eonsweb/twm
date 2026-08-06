<?php

namespace Database\Seeders;

use App\DonationCategoryType;
use App\Models\DonationCategory;
use Illuminate\Database\Seeder;

class DonationCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Tithe' => DonationCategoryType::Tithe, 'Offering' => DonationCategoryType::Offering, 'Thanksgiving' => DonationCategoryType::Thanksgiving, 'Building Fund' => DonationCategoryType::BuildingFund, 'Missions' => DonationCategoryType::Missions, 'Welfare' => DonationCategoryType::Welfare, 'Anniversary' => DonationCategoryType::Anniversary, 'General Donation' => DonationCategoryType::General] as $name => $type) {
            DonationCategory::withTrashed()->updateOrCreate(['slug' => str($name)->slug()], ['name' => $name, 'type' => $type, 'is_active' => true, 'display_on_website' => true]);
        }
    }
}
