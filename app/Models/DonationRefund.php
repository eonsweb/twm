<?php

namespace App\Models;

use Database\Factories\DonationRefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['donation_id', 'amount', 'reason', 'refunded_at', 'reference', 'processed_by'])]
class DonationRefund extends Model
{
    /** @use HasFactory<DonationRefundFactory> */
    use HasFactory;

    /** @return BelongsTo<Donation, $this> */
    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'refunded_at' => 'datetime'];
    }
}
