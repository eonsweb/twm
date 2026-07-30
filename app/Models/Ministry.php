<?php

namespace App\Models;

use App\MinistryStatus;
use Database\Factories\MinistryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $featured_image
 * @property string|null $logo
 * @property string|null $meeting_day
 * @property Carbon|null $meeting_time
 * @property string|null $meeting_location
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property int $display_order
 * @property MinistryStatus $status
 * @property bool $is_featured
 * @property Carbon|null $published_at
 * @property int|null $created_by
 * @property int|null $updated_by
 */
#[Fillable([
    'name',
    'slug',
    'short_description',
    'description',
    'mission',
    'vision',
    'featured_image',
    'logo',
    'meeting_day',
    'meeting_time',
    'meeting_location',
    'contact_email',
    'contact_phone',
    'display_order',
    'status',
    'is_featured',
    'published_at',
    'created_by',
    'updated_by',
])]
class Ministry extends Model
{
    /** @use HasFactory<MinistryFactory> */
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'display_order' => 0,
        'status' => 'draft',
        'is_featured' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (Ministry $ministry): void {
            if (! filled($ministry->slug)) {
                $ministry->slug = static::uniqueSlug($ministry->name);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsToMany<Person, $this> */
    public function leaders(): BelongsToMany
    {
        return $this->belongsToMany(Person::class)
            ->withPivot(['role_title', 'is_primary', 'display_order'])
            ->withTimestamps()
            ->orderByPivot('is_primary', 'desc')
            ->orderByPivot('display_order')
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /** @return BelongsToMany<Sermon, $this> */
    public function sermons(): BelongsToMany
    {
        return $this->belongsToMany(Sermon::class)->withTimestamps();
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
     * @param  Builder<Ministry>  $query
     * @return Builder<Ministry>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', MinistryStatus::Published)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now()));
    }

    /**
     * @param  Builder<Ministry>  $query
     * @return Builder<Ministry>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', MinistryStatus::Inactive);
    }

    /**
     * @param  Builder<Ministry>  $query
     * @return Builder<Ministry>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * @param  Builder<Ministry>  $query
     * @return Builder<Ministry>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    /**
     * @param  Builder<Ministry>  $query
     * @return Builder<Ministry>
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $term = '%'.trim($search).'%';

        return $query->where(fn (Builder $query): Builder => $query
            ->where('name', 'like', $term)
            ->orWhere('short_description', 'like', $term)
            ->orWhere('meeting_location', 'like', $term));
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status === MinistryStatus::Published
            && ($this->published_at === null || $this->published_at->isPast());
    }

    public function imageUrl(): ?string
    {
        return $this->featured_image === null ? null : Storage::disk('public')->url($this->featured_image);
    }

    public function logoUrl(): ?string
    {
        return $this->logo === null ? null : Storage::disk('public')->url($this->logo);
    }

    public static function uniqueSlug(string $value, int|string|null $exceptId = null): string
    {
        $base = Str::slug($value) ?: 'ministry';
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
            'meeting_time' => 'datetime:H:i',
            'display_order' => 'integer',
            'status' => MinistryStatus::class,
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
