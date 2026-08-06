<?php

namespace App\Models;

use App\Activity\ActivityLogger;
use App\DonationCategoryType;
use Database\Factories\DonationCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'description', 'type', 'suggested_amount', 'is_active', 'is_featured', 'display_on_website', 'sort_order', 'created_by'])]
class DonationCategory extends Model
{
    /** @use HasFactory<DonationCategoryFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saved(function (self $category): void {
            if (auth()->check()) {
                app(ActivityLogger::class)->log('donations', $category->wasRecentlyCreated ? 'donation-category.created' : 'donation-category.updated', 'Saved donation category.', null, auth()->user(), ['category_id' => $category->id]);
            }
        });
        static::deleted(function (self $category): void {
            if (auth()->check()) {
                app(ActivityLogger::class)->log('donations', 'donation-category.archived', 'Archived donation category.', null, auth()->user(), ['category_id' => $category->id]);
            }
        });
    }

    /** @return HasMany<Donation, $this> */
    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    /** @return HasMany<DonationCampaign, $this> */
    public function campaigns(): HasMany
    {
        return $this->hasMany(DonationCampaign::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return ['type' => DonationCategoryType::class, 'suggested_amount' => 'decimal:2', 'is_active' => 'boolean', 'is_featured' => 'boolean', 'display_on_website' => 'boolean'];
    }
}
