<?php

namespace App\Models;

use App\Activity\ActivityLogger;
use App\DonorType;
use Database\Factories\DonorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'first_name', 'last_name', 'email', 'phone', 'address', 'city', 'country', 'donor_type', 'notes', 'is_anonymous'])]
class Donor extends Model
{
    /** @use HasFactory<DonorFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saved(function (self $donor): void {
            if (auth()->check()) {
                app(ActivityLogger::class)->log('donations', $donor->wasRecentlyCreated ? 'donor.created' : 'donor.updated', 'Saved donor profile.', null, auth()->user(), ['donor_id' => $donor->id]);
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Donation, $this> */
    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function getFullNameAttribute(): string
    {
        return $this->is_anonymous ? 'Anonymous Donor' : trim($this->first_name.' '.$this->last_name);
    }

    protected function casts(): array
    {
        return ['donor_type' => DonorType::class, 'is_anonymous' => 'boolean'];
    }
}
