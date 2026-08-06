<?php

namespace Database\Factories;

use App\Models\Donation;
use App\Models\DonationRefund;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DonationRefund> */ class DonationRefundFactory extends Factory
{
    protected $model = DonationRefund::class;

    public function definition(): array
    {
        return ['donation_id' => Donation::factory()->completed(), 'amount' => 10, 'reason' => fake()->sentence(), 'refunded_at' => now(), 'reference' => fake()->unique()->uuid()];
    }
}
