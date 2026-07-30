<?php

namespace App\Models;

use App\Concerns\HasMedia;
use App\PostStatus;
use App\PostVisibility;
use Carbon\CarbonInterface;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $author_id
 * @property int|null $post_category_id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string|null $content
 * @property string|null $featured_image
 * @property string|null $featured_image_alt_text
 * @property PostStatus $status
 * @property PostVisibility $visibility
 * @property bool $is_featured
 * @property bool $allow_comments
 * @property Carbon|null $published_at
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $archived_at
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $canonical_url
 * @property Carbon|null $deleted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'author_id', 'post_category_id', 'title', 'slug', 'excerpt', 'content',
    'featured_image', 'featured_image_alt_text', 'status', 'visibility',
    'is_featured', 'allow_comments', 'published_at', 'scheduled_for',
    'archived_at', 'meta_title', 'meta_description', 'canonical_url',
])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, HasMedia, SoftDeletes;

    protected $attributes = [
        'status' => 'draft',
        'visibility' => 'public',
        'is_featured' => false,
        'allow_comments' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (Post $post): void {
            if (! filled($post->slug)) {
                $post->slug = static::uniqueSlug($post->title);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<PostCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'post_category_id');
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    /** @param Builder<Post> $query
     * @return Builder<Post>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Published);
    }

    /** @param Builder<Post> $query
     * @return Builder<Post>
     */
    public function scopeDrafts(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Draft);
    }

    /** @param Builder<Post> $query
     * @return Builder<Post>
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Scheduled);
    }

    /** @param Builder<Post> $query
     * @return Builder<Post>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Archived);
    }

    /** @param Builder<Post> $query
     * @return Builder<Post>
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('visibility', PostVisibility::Public)
            ->where(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->where('status', PostStatus::Published)
                        ->whereNotNull('published_at')
                        ->where('published_at', '<=', now());
                })->orWhere(function (Builder $query): void {
                    $query->where('status', PostStatus::Scheduled)
                        ->whereNotNull('scheduled_for')
                        ->where('scheduled_for', '<=', now());
                });
            });
    }

    /** @param Builder<Post> $query
     * @return Builder<Post>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /** @param Builder<Post> $query
     * @return Builder<Post>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $search = '%'.Str::squish($term).'%';

        return $query->where(function (Builder $query) use ($search): void {
            $query->where('title', 'like', $search)
                ->orWhere('excerpt', 'like', $search)
                ->orWhere('content', 'like', $search)
                ->orWhereHas('author', fn (Builder $author): Builder => $author->where('name', 'like', $search))
                ->orWhereHas('category', fn (Builder $category): Builder => $category->where('name', 'like', $search))
                ->orWhereHas('tags', fn (Builder $tags): Builder => $tags->where('name', 'like', $search));
        });
    }

    public function isPubliclyVisible(): bool
    {
        return $this->visibility === PostVisibility::Public
            && (($this->status === PostStatus::Published && $this->published_at?->lte(now()))
                || ($this->status === PostStatus::Scheduled && $this->scheduled_for?->lte(now())));
    }

    public function readingTime(): int
    {
        return max(1, (int) ceil(Str::wordCount(strip_tags($this->content ?? '')) / 225));
    }

    public function publicDate(): ?CarbonInterface
    {
        return $this->published_at ?? $this->scheduled_for;
    }

    public function imageUrl(): ?string
    {
        return $this->featured_image === null ? null : Storage::disk('public')->url($this->featured_image);
    }

    public function seoTitle(): string
    {
        return $this->meta_title ?: $this->title;
    }

    public function seoDescription(): string
    {
        return $this->meta_description ?: Str::limit(strip_tags($this->excerpt ?: $this->content ?? ''), 160);
    }

    public static function uniqueSlug(string $value, int|string|null $exceptId = null): string
    {
        $base = Str::slug($value) ?: 'post';
        $slug = $base;
        $suffix = 2;
        while (static::withTrashed()->when($exceptId !== null, fn (Builder $query): Builder => $query->whereKeyNot($exceptId))->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'visibility' => PostVisibility::class,
            'is_featured' => 'boolean',
            'allow_comments' => 'boolean',
            'published_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
