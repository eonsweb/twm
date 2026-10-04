<?php

namespace App\Models;

use App\Concerns\LogsActivity;
use App\MediaStatus;
use App\MediaType;
use App\MediaVisibility;
use Database\Factories\HomepageHeroSlideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $settings
 */
#[Fillable(['page_id', 'title', 'description', 'media_id', 'mobile_media_id', 'video_poster_media_id', 'emblem_media_id', 'media_type', 'cta_text', 'cta_url', 'secondary_cta_text', 'secondary_cta_url', 'overlay_opacity', 'sort_order', 'is_active', 'starts_at', 'ends_at', 'settings'])]
class HomepageHeroSlide extends Model
{
    /** @use HasFactory<HomepageHeroSlideFactory> */
    use HasFactory, LogsActivity;

    protected $attributes = ['sort_order' => 0, 'is_active' => true, 'media_type' => 'image'];

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /** @return BelongsTo<Media, $this> */
    public function mobileMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'mobile_media_id');
    }

    /** @return BelongsTo<Media, $this> */
    public function videoPosterMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'video_poster_media_id');
    }

    /** @return BelongsTo<Media, $this> */
    public function emblemMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'emblem_media_id');
    }

    /** @param Builder<HomepageHeroSlide> $query
     * @return Builder<HomepageHeroSlide>
     */
    public function scopeVisible(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    public static function usableMedia(?Media $media, bool $imageOnly = false): bool
    {
        return $media !== null
            && $media->visibility === MediaVisibility::Public
            && $media->status === MediaStatus::Active
            && ($media->media_type === MediaType::Image
                || (! $imageOnly && $media->media_type === MediaType::Video && in_array($media->mime_type, ['video/mp4', 'video/webm', 'video/ogg'], true)))
            && $media->existsOnDisk();
    }

    /** @return array{settings: array<string, mixed>, emblem: ?Media} */
    public function presentation(): array
    {
        return [
            'settings' => [
                ...($this->settings ?? []),
                'heading' => $this->title,
                'description' => $this->description,
                'primary_label' => $this->cta_text,
                'primary_url' => $this->cta_url,
                'secondary_label' => $this->secondary_cta_text,
                'secondary_url' => $this->secondary_cta_url,
            ],
            'emblem' => $this->emblemMedia,
        ];
    }

    protected function casts(): array
    {
        return ['settings' => 'array', 'is_active' => 'boolean', 'sort_order' => 'integer', 'overlay_opacity' => 'integer', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }
}
