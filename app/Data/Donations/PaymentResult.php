<?php

namespace App\Data\Donations;

use App\DonationPaymentStatus;

final readonly class PaymentResult
{
    /** @param array<string, mixed> $safeMetadata */
    public function __construct(
        public DonationPaymentStatus $status,
        public string $providerReference,
        public ?string $redirectUrl = null,
        public array $safeMetadata = [],
    ) {}
}
