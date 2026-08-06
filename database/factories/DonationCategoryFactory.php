<?php

namespace Database\Factories;

use App\DonationCategoryType;
use App\Models\DonationCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DonationCategory> */ class DonationCategoryFactory extends Factory
{
    protected $model = DonationCategory::class;

    public function definition(): array
    {
        $name = rtrim(fake()->unique()->sentence(2), '.');

        return ['name' => str($name)->title(), 'slug' => str($name)->slug().'-'.fake()->unique()->numberBetween(1, 99999), 'description' => fake()->sentence(), 'type' => fake()->randomElement(DonationCategoryType::cases()), 'suggested_amount' => fake()->randomElement([null, 25, 50, 100]), 'is_active' => true, 'is_featured' => false, 'display_on_website' => true, 'sort_order' => 0];
    }
}
