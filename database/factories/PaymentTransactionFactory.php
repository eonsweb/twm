<?php

namespace Database\Factories;

use App\DonationPaymentMethod;
use App\DonationPaymentStatus;
use App\Models\Donation;
use App\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentTransaction> */ class PaymentTransactionFactory extends Factory
{
    protected $model = PaymentTransaction::class;

    public function definition(): array
    {
        return ['donation_id' => Donation::factory(), 'provider' => 'manual', 'provider_reference' => fake()->unique()->uuid(), 'payment_method' => DonationPaymentMethod::BankTransfer, 'amount' => 100, 'currency' => 'USD', 'status' => DonationPaymentStatus::Pending];
    }
}
