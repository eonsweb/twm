<?php

namespace App\Contracts\Donations;

use App\Data\Donations\PaymentResult;
use App\Models\Donation;

interface PaymentProvider
{
    public function initiate(Donation $donation): PaymentResult;

    public function verify(string $providerReference): PaymentResult;
}
