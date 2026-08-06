<?php

namespace App\Models;

use App\DonationPaymentMethod;
use App\DonationPaymentStatus;
use Database\Factories\PaymentTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['donation_id', 'provider', 'provider_reference', 'payment_method', 'amount', 'currency', 'status', 'request_payload', 'response_payload', 'paid_at', 'failed_at', 'failure_reason'])]
class PaymentTransaction extends Model
{
    /** @use HasFactory<PaymentTransactionFactory> */
    use HasFactory;

    /** @return BelongsTo<Donation, $this> */
    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    protected function casts(): array
    {
        return ['payment_method' => DonationPaymentMethod::class, 'status' => DonationPaymentStatus::class, 'amount' => 'decimal:2', 'request_payload' => 'array', 'response_payload' => 'array', 'paid_at' => 'datetime', 'failed_at' => 'datetime'];
    }
}
