<?php

namespace App\Models;

use App\Concerns\HasMedia;
use App\Concerns\LogsActivity;
use App\PageStatus;
use App\PageTemplate;
use App\PageType;
use App\PageVisibility;
use Database\Factories\PageFactory;
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
 * @property string $title
 * @property string $slug
 * @property PageType $page_type
 * @property PageTemplate $template
 * @property string|null $excerpt
 * @property string|null $content
 * @property int|null $featured_image_id
 * @property PageStatus $status
 * @property PageVisibility $visibility
 * @property bool $is_homepage
 * @property bool $show_in_navigation
 * @property string|null $navigation_label
 * @property int|null $navigation_order
 * @property int|null $parent_id
 * @property Carbon|null $published_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $og_image_id
 */
#[Fillable([
    'title', 'slug', 'page_type', 'template', 'excerpt', 'content', 'featured_image_id',
    'status', 'visibility', 'is_homepage', 'show_in_navigation', 'navigation_label',
    'navigation_order', 'parent_id', 'published_at', 'created_by', 'updated_by',
    'meta_title', 'meta_description', 'meta_keywords', 'canonical_url', 'robots_index',
    'robots_follow', 'og_title', 'og_description', 'og_image_id',
])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, HasMedia, LogsActivity, SoftDeletes;

    public const RESERVED_SLUGS = ['admin', 'api', 'login', 'logout', 'register', 'dashboard', 'settings', 'password', 'sermons', 'sermon-series', 'speakers', 'events', 'ministries', 'books', 'prayer-request', 'answered-prayers', 'contact', 'give', 'blog', 'media', 'users', 'roles'];

    protected $attributes = ['page_type' => 'standard', 'template' => 'default', 'status' => 'draft', 'visibility' => 'public', 'is_homepage' => false, 'show_in_navigation' => false, 'robots_index' => true, 'robots_follow' => true];

    protected static function booted(): void
    {
        static::creating(function (Page $page): void {
            $page->slug = static::uniqueSlug($page->slug ?: $page->title);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<Page, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Page, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('navigation_order');
    }

    /** @return HasMany<PageSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class)->orderBy('sort_order');
    }

    /** @return BelongsTo<Media, $this> */
    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_id');
    }

    /** @return BelongsTo<Media, $this> */
    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'og_image_id');
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

    /** @param Builder<Page> $query
     * @return Builder<Page>
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('status', PageStatus::Published)->where('visibility', PageVisibility::Public)->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /** @param Builder<Page> $query
     * @return Builder<Page>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $search = '%'.Str::squish($term).'%';

        return $query->where(fn (Builder $query): Builder => $query->where('title', 'like', $search)->orWhere('slug', 'like', $search)->orWhere('navigation_label', 'like', $search)->orWhere('excerpt', 'like', $search));
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status === PageStatus::Published && $this->visibility === PageVisibility::Public && $this->published_at?->lte(now());
    }

    public static function uniqueSlug(string $value, int|string|null $exceptId = null): string
    {
        $base = Str::slug($value) ?: 'page';
        $slug = $base;
        $suffix = 2;
        while (in_array($slug, self::RESERVED_SLUGS, true) || static::withTrashed()->when($exceptId !== null, fn (Builder $query): Builder => $query->whereKeyNot($exceptId))->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    protected function casts(): array
    {
        return ['page_type' => PageType::class, 'template' => PageTemplate::class, 'status' => PageStatus::class, 'visibility' => PageVisibility::class, 'is_homepage' => 'boolean', 'show_in_navigation' => 'boolean', 'robots_index' => 'boolean', 'robots_follow' => 'boolean', 'published_at' => 'datetime'];
    }
}
