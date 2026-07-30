<?php

namespace App\Models;

use Database\Factories\PostCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'description', 'is_active', 'sort_order'])]
class PostCategory extends Model
{
    /** @use HasFactory<PostCategoryFactory> */
    use HasFactory;

    protected $attributes = ['is_active' => true, 'sort_order' => 0];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<Post, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public static function uniqueSlug(string $value, int|string|null $exceptId = null): string
    {
        $base = Str::slug($value) ?: 'category';
        $slug = $base;
        $suffix = 2;

        while (static::query()->when($exceptId !== null, fn (Builder $query): Builder => $query->whereKeyNot($exceptId))->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }
}
