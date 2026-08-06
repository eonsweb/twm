<?php

namespace App\Models;

use App\Activity\ActivityLogger;
use App\DonationCampaignStatus;
use App\DonationPaymentStatus;
use Database\Factories\DonationCampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['donation_category_id', 'name', 'slug', 'description', 'goal_amount', 'start_date', 'end_date', 'status', 'featured_image_id', 'is_featured', 'display_on_website', 'created_by'])]
class DonationCampaign extends Model
{
    /** @use HasFactory<DonationCampaignFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saved(function (self $campaign): void {
            if (auth()->check()) {
                app(ActivityLogger::class)->log('donations', $campaign->wasRecentlyCreated ? 'donation-campaign.created' : 'donation-campaign.updated', 'Saved donation campaign.', null, auth()->user(), ['campaign_id' => $campaign->id]);
            }
        });
    }

    /** @return BelongsTo<DonationCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DonationCategory::class, 'donation_category_id');
    }

    /** @return HasMany<Donation, $this> */
    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Media, $this> */
    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_id');
    }

    public function completedTotal(): string
    {
        return (string) $this->donations()->where('payment_status', DonationPaymentStatus::Completed)->sum('amount');
    }

    protected function casts(): array
    {
        return ['status' => DonationCampaignStatus::class, 'goal_amount' => 'decimal:2', 'start_date' => 'date', 'end_date' => 'date', 'is_featured' => 'boolean', 'display_on_website' => 'boolean'];
    }
}
