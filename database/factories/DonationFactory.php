<?php

namespace Database\Factories;

use App\DonationPaymentMethod;
use App\DonationPaymentStatus;
use App\DonationSource;
use App\Models\Donation;
use App\Models\DonationCategory;
use App\Models\Donor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Donation> */ class DonationFactory extends Factory
{
    protected $model = Donation::class;

    public function definition(): array
    {
        return ['reference' => 'DON-PENDING-'.Str::uuid(), 'donor_id' => Donor::factory(), 'donation_category_id' => DonationCategory::factory(), 'amount' => fake()->randomFloat(2, 5, 5000), 'currency' => 'USD', 'payment_method' => fake()->randomElement(DonationPaymentMethod::cases()), 'payment_status' => DonationPaymentStatus::Pending, 'donated_at' => fake()->dateTimeBetween('-1 year'), 'is_anonymous' => false, 'is_recurring' => false, 'source' => DonationSource::Admin];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['payment_status' => DonationPaymentStatus::Completed, 'approved_at' => now()]);
    }
}
