<?php

namespace App\Actions\Donations;

use App\Activity\ActivityLogger;
use App\DonationPaymentStatus;
use App\Models\Donation;
use App\Models\DonationRefund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RefundDonation
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(User $actor, Donation $donation, string $amount, string $reason, ?string $reference = null): DonationRefund
    {
        Gate::forUser($actor)->authorize('refund', $donation);
        if (! in_array($donation->payment_status, [DonationPaymentStatus::Completed, DonationPaymentStatus::PartiallyRefunded], true)) {
            throw ValidationException::withMessages(['refundAmount' => 'Only completed donations can be refunded.']);
        } $remaining = (float) $donation->amount - (float) $donation->refunds()->sum('amount');
        if ((float) $amount <= 0 || (float) $amount > $remaining) {
            throw ValidationException::withMessages(['refundAmount' => 'Refund amount exceeds the remaining paid amount.']);
        }
        $refund = DB::transaction(function () use ($donation, $actor, $amount, $reason, $reference, $remaining) {
            $r = $donation->refunds()->create(['amount' => $amount, 'reason' => $reason, 'refunded_at' => now(), 'reference' => $reference, 'processed_by' => $actor->id]);
            $status = (float) $amount === $remaining ? DonationPaymentStatus::Refunded : DonationPaymentStatus::PartiallyRefunded;
            $donation->forceFill(['payment_status' => $status])->save();
            $donation->transactions()->update(['status' => $status]);

            return $r;
        }, 3);
        $this->logger->log('donations', 'donation.refunded', 'Recorded a manual refund for '.$donation->reference.'.', null, $actor, ['donation_id' => $donation->id, 'refund_id' => $refund->id, 'amount' => $amount]);

        return $refund;
    }
}
