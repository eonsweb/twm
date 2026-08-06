<?php

namespace Database\Seeders;

use App\Models\Donation;
use App\Models\DonationCampaign;
use App\Models\DonationCategory;
use App\Models\Donor;
use Illuminate\Database\Seeder;

class DonationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DonationCategorySeeder::class);
        if (Donation::query()->exists()) {
            return;
        }$category = DonationCategory::firstOrFail();
        $donors = Donor::factory(8)->create();
        DonationCampaign::factory(2)->create(['donation_category_id' => $category->id]);
        foreach ($donors as $donor) {
            Donation::factory()->count(2)->create(['donor_id' => $donor->id, 'donation_category_id' => $category->id]);
        }Donation::factory()->completed()->count(5)->create(['donation_category_id' => $category->id]);
    }
}
