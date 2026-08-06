<?php

namespace Database\Factories;

use App\DonationCampaignStatus;
use App\Models\DonationCampaign;
use App\Models\DonationCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DonationCampaign> */ class DonationCampaignFactory extends Factory
{
    protected $model = DonationCampaign::class;

    public function definition(): array
    {
        $name = rtrim(fake()->unique()->sentence(3), '.');

        return ['donation_category_id' => DonationCategory::factory(), 'name' => str($name)->title(), 'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1, 99999), 'description' => fake()->paragraph(), 'goal_amount' => fake()->numberBetween(1000, 50000), 'start_date' => now()->subMonth(), 'end_date' => now()->addMonths(3), 'status' => DonationCampaignStatus::Active, 'is_featured' => false, 'display_on_website' => true];
    }
}
