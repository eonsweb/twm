<?php

namespace App\Models;

use App\DonationPaymentMethod;
use App\DonationPaymentStatus;
use App\DonationSource;
use App\RecurringFrequency;
use App\Settings\SettingManager;
use Database\Factories\DonationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $reference
 * @property string|null $receipt_number
 * @property string $amount
 * @property string $currency
 * @property DonationPaymentMethod $payment_method
 * @property DonationPaymentStatus $payment_status
 * @property DonationSource $source
 * @property Carbon $donated_at
 * @property Carbon|null $approved_at
 * @property Carbon $created_at
 * @property bool $is_anonymous
 * @property bool $is_recurring
 * @property string|null $provider_reference
 * @property string|null $payment_provider
 * @property string|null $transaction_reference
 * @property string|null $notes
 * @property string|null $internal_notes
 * @property int|null $recorded_by
 * @property int|null $approved_by
 * @property-read Donor|null $donor
 * @property-read DonationCategory $category
 * @property-read DonationCampaign|null $campaign
 * @property-read User|null $recorder
 * @property-read User|null $approver
 */
#[Fillable(['reference', 'public_submission_key', 'receipt_number', 'donor_id', 'donation_category_id', 'donation_campaign_id', 'amount', 'currency', 'payment_method', 'payment_status', 'payment_provider', 'provider_reference', 'transaction_reference', 'donated_at', 'is_anonymous', 'is_recurring', 'recurring_frequency', 'source', 'notes', 'internal_notes', 'metadata', 'recorded_by', 'approved_by', 'approved_at'])]
class Donation extends Model
{
    /** @use HasFactory<DonationFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(fn (self $d) => $d->reference ??= 'DON-PENDING-'.Str::uuid());
        static::created(fn (self $d) => $d->forceFill(['reference' => sprintf('DON-%d-%06d', $d->created_at->year, $d->id)])->saveQuietly());
    }

    /** @return BelongsTo<Donor, $this> */
    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    /** @return BelongsTo<DonationCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DonationCategory::class, 'donation_category_id');
    }

    /** @return BelongsTo<DonationCampaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(DonationCampaign::class, 'donation_campaign_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<PaymentTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    /** @return HasMany<DonationRefund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(DonationRefund::class);
    }

    /** @param Builder<Donation> $query
     * @return Builder<Donation>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('payment_status', DonationPaymentStatus::Completed);
    }

    /** @param Builder<Donation> $query
     * @return Builder<Donation>
     */
    public function scopeSearch(Builder $query, string $value): Builder
    {
        $term = '%'.Str::squish($value).'%';

        return $query->where(fn (Builder $q) => $q->where('reference', 'like', $term)->orWhere('receipt_number', 'like', $term)->orWhere('provider_reference', 'like', $term)->orWhereHas('donor', fn (Builder $d) => $d->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term)));
    }

    public function donorLabel(): string
    {
        return $this->is_anonymous || ! $this->donor ? 'Anonymous Donor' : $this->donor->full_name;
    }

    public function totalRefunded(): string
    {
        return number_format((float) $this->refunds()->sum('amount'), 2, '.', '');
    }

    public function issueReceipt(): void
    {
        if (! $this->receipt_number) {
            $prefix = (string) app(SettingManager::class)->get('donations', 'receipt_prefix', 'TWM-RCP');
            $this->forceFill(['receipt_number' => sprintf('%s-%d-%06d', $prefix, $this->created_at->year, $this->id)])->save();
        }
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'payment_method' => DonationPaymentMethod::class, 'payment_status' => DonationPaymentStatus::class, 'source' => DonationSource::class, 'recurring_frequency' => RecurringFrequency::class, 'donated_at' => 'datetime', 'approved_at' => 'datetime', 'is_anonymous' => 'boolean', 'is_recurring' => 'boolean', 'metadata' => 'array'];
    }
}
