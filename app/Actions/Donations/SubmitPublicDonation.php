<?php

namespace App\Actions\Donations;

use App\DonationPaymentStatus;
use App\DonationSource;
use App\DonorType;
use App\Models\Donation;
use App\Models\DonationCampaign;
use App\Models\Donor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitPublicDonation
{
    /** @param array<string,mixed> $data */
    public function handle(array $data): Donation
    {
        $existing = Donation::query()->where('public_submission_key', $data['submission_key'])->first();
        if ($existing !== null) {
            return $existing;
        }
        if (filled($data['donation_campaign_id'] ?? null)) {
            $campaign = DonationCampaign::query()->find((int) $data['donation_campaign_id']);
            if ($campaign === null || ($campaign->donation_category_id !== null && (int) $campaign->donation_category_id !== (int) $data['donation_category_id'])) {
                throw ValidationException::withMessages(['campaignId' => 'The campaign does not belong to the selected category.']);
            }
        }

        return DB::transaction(function () use ($data) {
            $anonymous = (bool) ($data['is_anonymous'] ?? false);
            $donor = $anonymous ? null : Donor::query()->when(filled($data['email'] ?? null), fn ($q) => $q->where('email', $data['email']))->when(blank($data['email'] ?? null) && filled($data['phone'] ?? null), fn ($q) => $q->where('phone', $data['phone']))->first();
            $donor ??= $anonymous ? null : Donor::create(['first_name' => $data['first_name'], 'last_name' => $data['last_name'] ?? '', 'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null, 'donor_type' => DonorType::Visitor, 'is_anonymous' => false]);

            return Donation::create(['public_submission_key' => $data['submission_key'], 'donor_id' => $donor?->id, 'donation_category_id' => $data['donation_category_id'], 'donation_campaign_id' => $data['donation_campaign_id'] ?? null, 'amount' => $data['amount'], 'currency' => $data['currency'], 'payment_method' => $data['payment_method'], 'payment_status' => DonationPaymentStatus::Pending, 'donated_at' => now(), 'is_anonymous' => $anonymous, 'is_recurring' => false, 'source' => DonationSource::Website, 'notes' => $data['notes'] ?? null]);
        }, 3);
    }
}
