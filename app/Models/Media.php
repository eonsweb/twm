<?php

namespace App\Models;

use App\Concerns\LogsActivity;
use App\MediaStatus;
use App\MediaType;
use App\MediaVisibility;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property MediaType $media_type
 * @property MediaVisibility $visibility
 * @property MediaStatus $status
 */
#[Fillable([
    'media_folder_id', 'uploaded_by', 'updated_by', 'name', 'original_name', 'file_name',
    'slug', 'disk', 'directory', 'path', 'mime_type', 'extension', 'media_type', 'size',
    'width', 'height', 'duration', 'alt_text', 'caption', 'description', 'copyright',
    'credit', 'source_url', 'visibility', 'status', 'metadata', 'is_featured',
    'download_count', 'last_used_at',
])]
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $attributes = [
        'visibility' => 'public',
        'status' => 'active',
        'is_featured' => false,
        'download_count' => 0,
    ];

    /** @return BelongsTo<MediaFolder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'media_folder_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return HasMany<Mediable, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(Mediable::class);
    }

    public function publicUrl(): ?string
    {
        return $this->visibility === MediaVisibility::Public
            ? Storage::disk($this->disk)->url($this->path)
            : null;
    }

    public function publicImageUrl(): ?string
    {
        if ($this->media_type !== MediaType::Image
            || $this->visibility !== MediaVisibility::Public
            || $this->status !== MediaStatus::Active
            || ! $this->existsOnDisk()) {
            return null;
        }

        return $this->publicUrl();
    }

    public function existsOnDisk(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    /** @param Builder<Media> $query
     * @return Builder<Media>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $search = '%'.Str::squish($term).'%';

        return $query->where(function (Builder $query) use ($search): void {
            $query->where('name', 'like', $search)
                ->orWhere('original_name', 'like', $search)
                ->orWhere('alt_text', 'like', $search)
                ->orWhere('caption', 'like', $search)
                ->orWhere('description', 'like', $search);
        });
    }

    /** @param Builder<Media> $query
     * @return Builder<Media>
     */
    public function scopeImages(Builder $query): Builder
    {
        return $query->where('media_type', MediaType::Image);
    }

    /** @param Builder<Media> $query
     * @return Builder<Media>
     */
    public function scopeVideos(Builder $query): Builder
    {
        return $query->where('media_type', MediaType::Video);
    }

    /** @param Builder<Media> $query
     * @return Builder<Media>
     */
    public function scopeAudio(Builder $query): Builder
    {
        return $query->where('media_type', MediaType::Audio);
    }

    /** @param Builder<Media> $query
     * @return Builder<Media>
     */
    public function scopeDocuments(Builder $query): Builder
    {
        return $query->whereIn('media_type', [
            MediaType::Document, MediaType::Spreadsheet, MediaType::Presentation,
        ]);
    }

    /** @param Builder<Media> $query
     * @return Builder<Media>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', MediaVisibility::Public);
    }

    /** @param Builder<Media> $query
     * @return Builder<Media>
     */
    public function scopePrivate(Builder $query): Builder
    {
        return $query->where('visibility', MediaVisibility::Private);
    }

    /** @param Builder<Media> $query
     * @return Builder<Media>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', MediaStatus::Active);
    }

    /** @param Builder<Media> $query
     * @return Builder<Media>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', MediaStatus::Archived);
    }

    /** @param Builder<Media> $query
     * @return Builder<Media>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /** @param Builder<Media> $query
     * @return Builder<Media>
     */
    public function scopeRecent(Builder $query): Builder
    {
        return $query->latest();
    }

    protected function casts(): array
    {
        return [
            'media_type' => MediaType::class,
            'visibility' => MediaVisibility::class,
            'status' => MediaStatus::class,
            'metadata' => 'array',
            'is_featured' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }
}
