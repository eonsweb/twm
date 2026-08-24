<?php

namespace App\Models;

use App\BookAvailabilityStatus;
use App\BookFormat;
use App\BookStatus;
use App\MediaStatus;
use App\MediaType;
use App\MediaVisibility;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $author_name
 * @property int|null $leadership_id
 * @property int|null $speaker_id
 * @property string|null $short_description
 * @property string|null $download_url
 * @property string $purchase_url
 * @property int|null $media_id
 * @property int|null $audio_sample_media_id
 * @property BookFormat $format
 * @property string|null $price
 * @property string $currency
 * @property BookAvailabilityStatus $availability_status
 * @property bool $is_featured
 * @property bool $is_free
 * @property BookStatus $status
 * @property Carbon|null $publication_date
 * @property Carbon|null $published_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $deleted_at
 * @property-read Media|null $cover
 * @property-read Media|null $audioSample
 */
#[Fillable([
    'title', 'slug', 'subtitle', 'author_name', 'leadership_id', 'speaker_id',
    'description', 'short_description', 'isbn', 'publisher', 'publication_date',
    'edition', 'language', 'page_count', 'format', 'price', 'currency',
    'stock_quantity', 'availability_status', 'purchase_url', 'download_url',
    'media_id', 'audio_sample_media_id', 'is_featured', 'is_free', 'status', 'published_at',
    'created_by', 'updated_by',
])]
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'format' => 'physical',
        'currency' => 'GHS',
        'language' => 'English',
        'availability_status' => 'available',
        'is_featured' => false,
        'is_free' => false,
        'status' => 'draft',
    ];

    protected static function booted(): void
    {
        static::creating(function (Book $book): void {
            if (! filled($book->slug)) {
                $book->slug = static::uniqueSlug($book->title);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<Person, $this> */
    public function leadership(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'leadership_id');
    }

    /** @return BelongsTo<Person, $this> */
    public function speaker(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'speaker_id');
    }

    /** @return BelongsTo<Media, $this> */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    /** @return BelongsTo<Media, $this> */
    public function audioSample(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'audio_sample_media_id');
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

    /** @param Builder<Book> $query
     * @return Builder<Book>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', BookStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /** @param Builder<Book> $query
     * @return Builder<Book>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', BookStatus::Draft);
    }

    /** @param Builder<Book> $query
     * @return Builder<Book>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', BookStatus::Archived);
    }

    /** @param Builder<Book> $query
     * @return Builder<Book>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /** @param Builder<Book> $query
     * @return Builder<Book>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereIn('availability_status', [
            BookAvailabilityStatus::Available,
            BookAvailabilityStatus::Preorder,
        ]);
    }

    /** @param Builder<Book> $query
     * @return Builder<Book>
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $term = '%'.Str::squish($search).'%';

        return $query->where(fn (Builder $query): Builder => $query
            ->where('title', 'like', $term)
            ->orWhere('subtitle', 'like', $term)
            ->orWhere('author_name', 'like', $term)
            ->orWhere('isbn', 'like', $term)
            ->orWhere('publisher', 'like', $term)
            ->orWhere('short_description', 'like', $term));
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status === BookStatus::Published
            && $this->published_at?->isPast();
    }

    public function coverUrl(): ?string
    {
        return $this->cover?->publicImageUrl();
    }

    /** @return Attribute<string, never> */
    protected function purchaseUrl(): Attribute
    {
        return Attribute::get(fn (?string $value): string => filled($value)
            ? $value
            : route('public.books.show', $this));
    }

    public function isPurchaseUrlExternal(): bool
    {
        $purchaseHost = Str::lower((string) parse_url($this->purchase_url, PHP_URL_HOST));
        $publicBookHost = Str::lower((string) parse_url(route('public.books.show', $this), PHP_URL_HOST));

        return $purchaseHost !== '' && $purchaseHost !== $publicBookHost;
    }

    public function audioSampleUrl(): ?string
    {
        $audioSample = $this->audioSample;

        if ($audioSample === null
            || $audioSample->media_type !== MediaType::Audio
            || $audioSample->visibility !== MediaVisibility::Public
            || $audioSample->status !== MediaStatus::Active
            || ! $audioSample->existsOnDisk()) {
            return null;
        }

        return $audioSample->publicUrl();
    }

    public function displayPrice(): string
    {
        if ($this->is_free) {
            return __('Free');
        }

        $formatted = Number::currency((float) ($this->price ?? 0), in: $this->currency);

        return is_string($formatted)
            ? $formatted
            : $this->currency.' '.number_format((float) ($this->price ?? 0), 2);
    }

    public static function uniqueSlug(string $value, int|string|null $exceptId = null): string
    {
        $base = Str::slug($value) ?: 'book';
        $slug = $base;
        $suffix = 2;

        while (static::withTrashed()
            ->when($exceptId !== null, fn (Builder $query): Builder => $query->whereKeyNot($exceptId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    protected function casts(): array
    {
        return [
            'publication_date' => 'date',
            'format' => BookFormat::class,
            'price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'availability_status' => BookAvailabilityStatus::class,
            'is_featured' => 'boolean',
            'is_free' => 'boolean',
            'status' => BookStatus::class,
            'published_at' => 'datetime',
        ];
    }
}
