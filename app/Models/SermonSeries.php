<?php

namespace App\Models;

use App\SermonSeriesStatus;
use Database\Factories\SermonSeriesFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'title',
    'slug',
    'description',
    'cover_image_path',
    'starts_at',
    'ends_at',
    'status',
    'is_featured',
    'created_by',
    'updated_by',
])]
class SermonSeries extends Model
{
    /** @use HasFactory<SermonSeriesFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'sermon_series';

    protected $attributes = [
        'status' => 'draft',
        'is_featured' => false,
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<Sermon, $this> */
    public function sermons(): HasMany
    {
        return $this->hasMany(Sermon::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @param  Builder<SermonSeries>  $query
     * @return Builder<SermonSeries>
     */
    public function scopePubliclyAvailable(Builder $query): Builder
    {
        return $query->where('status', SermonSeriesStatus::Published);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'status' => SermonSeriesStatus::class,
            'is_featured' => 'boolean',
        ];
    }
}
