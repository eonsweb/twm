<?php

namespace App\Actions\Donations;

use App\Activity\ActivityLogger;
use App\DonationPaymentStatus;
use App\Models\Donation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChangeDonationStatus
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(User $actor, Donation $donation, DonationPaymentStatus $status): Donation
    {
        Gate::forUser($actor)->authorize('approve', $donation);
        $allowed = [
            DonationPaymentStatus::Pending->value => [DonationPaymentStatus::Pending, DonationPaymentStatus::Processing, DonationPaymentStatus::Completed, DonationPaymentStatus::Failed, DonationPaymentStatus::Cancelled],
            DonationPaymentStatus::Processing->value => [DonationPaymentStatus::Pending, DonationPaymentStatus::Processing, DonationPaymentStatus::Completed, DonationPaymentStatus::Failed, DonationPaymentStatus::Cancelled],
            DonationPaymentStatus::Failed->value => [DonationPaymentStatus::Failed, DonationPaymentStatus::Pending, DonationPaymentStatus::Processing, DonationPaymentStatus::Cancelled],
            DonationPaymentStatus::Cancelled->value => [DonationPaymentStatus::Cancelled, DonationPaymentStatus::Pending],
            DonationPaymentStatus::Completed->value => [DonationPaymentStatus::Completed],
            DonationPaymentStatus::PartiallyRefunded->value => [DonationPaymentStatus::PartiallyRefunded],
            DonationPaymentStatus::Refunded->value => [DonationPaymentStatus::Refunded],
        ];
        if (! in_array($status, $allowed[$donation->payment_status->value], true)) {
            throw ValidationException::withMessages(['status' => 'That payment status transition is not allowed.']);
        }
        $old = $donation->payment_status->value;
        $hadReceipt = filled($donation->receipt_number);
        DB::transaction(function () use ($donation, $status, $actor) {
            $donation->forceFill(['payment_status' => $status, 'approved_by' => $status === DonationPaymentStatus::Completed ? $actor->id : $donation->approved_by, 'approved_at' => $status === DonationPaymentStatus::Completed ? now() : $donation->approved_at])->save();
            $donation->transactions()->update([
                'status' => $status,
                'paid_at' => $status === DonationPaymentStatus::Completed ? now() : null,
                'failed_at' => $status === DonationPaymentStatus::Failed ? now() : null,
            ]);
            if ($status === DonationPaymentStatus::Completed) {
                $donation->issueReceipt();
            }
        }, 3);
        $this->logger->log('donations', 'donation.status-changed', 'Changed donation '.$donation->reference.' status.', null, $actor, ['donation_id' => $donation->id], ['status' => $old], ['status' => $status->value]);
        if (! $hadReceipt && filled($donation->refresh()->receipt_number)) {
            $this->logger->log('donations', 'donation.receipt-issued', 'Issued a donation receipt.', null, $actor, ['donation_id' => $donation->id, 'receipt_number' => $donation->receipt_number]);
        }

        return $donation->refresh();
    }
}
