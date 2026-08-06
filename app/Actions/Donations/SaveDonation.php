<?php

namespace App\Actions\Donations;

use App\Activity\ActivityLogger;
use App\DonationPaymentStatus;
use App\Models\Donation;
use App\Models\DonationCampaign;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveDonation
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /** @param array<string,mixed> $data */
    public function handle(User $actor, array $data, ?Donation $donation = null): Donation
    {
        $creating = $donation === null;
        Gate::forUser($actor)->authorize($creating ? 'create' : 'update', $donation ?? Donation::class);
        $currentStatus = $donation->payment_status ?? DonationPaymentStatus::Pending;
        $statusValue = $data['payment_status'] ?? $currentStatus;
        $requestedStatus = $statusValue instanceof DonationPaymentStatus
            ? $statusValue
            : DonationPaymentStatus::from((string) $statusValue);
        if ($requestedStatus === DonationPaymentStatus::Completed && ($creating || $currentStatus !== DonationPaymentStatus::Completed)) {
            Gate::forUser($actor)->authorize('approve', $donation ?? new Donation);
            $data['approved_by'] = $actor->id;
            $data['approved_at'] = now();
        }
        if (filled($data['donation_campaign_id'] ?? null)) {
            $campaign = DonationCampaign::find((int) $data['donation_campaign_id']);
            if (! $campaign || ($campaign->donation_category_id !== null && (int) $campaign->donation_category_id !== (int) $data['donation_category_id'])) {
                throw ValidationException::withMessages(['donation_campaign_id' => 'The campaign does not belong to the selected category.']);
            }
        }
        $donation ??= new Donation;
        $data['recorded_by'] ??= $creating ? $actor->id : $donation->recorded_by;
        $saved = DB::transaction(function () use ($donation, $data) {
            $donation->fill($data)->save();
            $paymentReference = $donation->provider_reference ?: $donation->transaction_reference;
            if (filled($paymentReference)) {
                $donation->transactions()->updateOrCreate(
                    ['provider_reference' => $paymentReference],
                    [
                        'provider' => $donation->payment_provider,
                        'payment_method' => $donation->payment_method,
                        'amount' => $donation->amount,
                        'currency' => $donation->currency,
                        'status' => $donation->payment_status,
                        'paid_at' => $donation->payment_status === DonationPaymentStatus::Completed ? ($donation->approved_at ?? now()) : null,
                        'failed_at' => $donation->payment_status === DonationPaymentStatus::Failed ? now() : null,
                    ],
                );
            }
            if ($donation->payment_status === DonationPaymentStatus::Completed) {
                $donation->issueReceipt();
            }

            return $donation->refresh();
        }, 3);
        $this->logger->log('donations', $creating ? 'donation.created' : 'donation.updated', ($creating ? 'Created' : 'Updated').' donation '.$saved->reference.'.', null, $actor, ['donation_id' => $saved->id, 'reference' => $saved->reference]);
        if ($creating && filled($saved->receipt_number)) {
            $this->logger->log('donations', 'donation.receipt-issued', 'Issued a donation receipt.', null, $actor, ['donation_id' => $saved->id, 'receipt_number' => $saved->receipt_number]);
        }

        return $saved;
    }
}
