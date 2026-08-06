<?php

namespace Database\Factories;

use App\DonorType;
use App\Models\Donor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Donor> */ class DonorFactory extends Factory
{
    protected $model = Donor::class;

    public function definition(): array
    {
        return ['first_name' => fake()->firstName(), 'last_name' => fake()->lastName(), 'email' => fake()->unique()->safeEmail(), 'phone' => fake()->phoneNumber(), 'city' => fake()->city(), 'country' => fake()->country(), 'donor_type' => DonorType::Visitor, 'is_anonymous' => false];
    }
}
