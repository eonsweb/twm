<?php

namespace App\Models;

use App\SermonMediaPlatform;
use App\SermonMediaType;
use App\SermonStatus;
use Database\Factories\SermonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $summary
 * @property string|null $description
 * @property string|null $scripture_reference
 * @property Carbon $sermon_date
 * @property int|null $duration_seconds
 * @property string $external_media_url
 * @property SermonMediaPlatform $media_platform
 * @property SermonMediaType $media_type
 * @property string|null $embed_url
 * @property string|null $thumbnail_path
 * @property string|null $external_thumbnail_url
 * @property int $speaker_id
 * @property string|null $service_name
 * @property string|null $location
 * @property SermonStatus $status
 * @property Carbon|null $published_at
 * @property Carbon|null $scheduled_at
 * @property bool $is_featured
 * @property int $display_order
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $deleted_at
 * @property-read Person $speaker
 */
#[Fillable([
    'title',
    'slug',
    'summary',
    'description',
    'scripture_reference',
    'sermon_date',
    'duration_seconds',
    'external_media_url',
    'media_platform',
    'media_type',
    'embed_url',
    'thumbnail_path',
    'external_thumbnail_url',
    'speaker_id',
    'service_name',
    'location',
    'status',
    'published_at',
    'scheduled_at',
    'is_featured',
    'display_order',
    'seo_title',
    'seo_description',
    'created_by',
    'updated_by',
])]
class Sermon extends Model
{
    /** @use HasFactory<SermonFactory> */
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'status' => 'draft',
        'is_featured' => false,
        'display_order' => 0,
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<Person, $this> */
    public function speaker(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'speaker_id');
    }

    /** @return BelongsToMany<Ministry, $this> */
    public function ministries(): BelongsToMany
    {
        return $this->belongsToMany(Ministry::class)->withTimestamps();
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
     * @param  Builder<Sermon>  $query
     * @return Builder<Sermon>
     */
    public function scopePubliclyAvailable(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->where(function (Builder $query): void {
                    $query->where('status', SermonStatus::Published)
                        ->where(function (Builder $query): void {
                            $query->whereNull('published_at')
                                ->orWhere('published_at', '<=', now());
                        });
                })
                ->orWhere(function (Builder $query): void {
                    $query->where('status', SermonStatus::Scheduled)
                        ->whereNotNull('scheduled_at')
                        ->where('scheduled_at', '<=', now());
                });
        });
    }

    public function isPubliclyAvailable(): bool
    {
        return match ($this->status) {
            SermonStatus::Published => $this->published_at === null || $this->published_at->isPast(),
            SermonStatus::Scheduled => $this->scheduled_at !== null && $this->scheduled_at->isPast(),
            default => false,
        };
    }

    public function thumbnailUrl(): ?string
    {
        return $this->thumbnail_path !== null
            ? Storage::disk('public')->url($this->thumbnail_path)
            : $this->external_thumbnail_url;
    }

    public function formattedDuration(): ?string
    {
        if ($this->duration_seconds === null) {
            return null;
        }

        $hours = intdiv($this->duration_seconds, 3600);
        $minutes = intdiv($this->duration_seconds % 3600, 60);
        $seconds = $this->duration_seconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $seconds)
            : sprintf('%d:%02d', $minutes, $seconds);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sermon_date' => 'date',
            'media_platform' => SermonMediaPlatform::class,
            'media_type' => SermonMediaType::class,
            'status' => SermonStatus::class,
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'is_featured' => 'boolean',
            'duration_seconds' => 'integer',
            'display_order' => 'integer',
        ];
    }
}
