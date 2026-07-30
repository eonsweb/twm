<?php

namespace App\Models;

use App\Concerns\LogsActivity;
use Database\Factories\MediaFolderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['parent_id', 'name', 'slug', 'description', 'created_by', 'updated_by'])]
class MediaFolder extends Model
{
    /** @use HasFactory<MediaFolderFactory> */
    use HasFactory, LogsActivity;

    protected static function booted(): void
    {
        static::saving(function (MediaFolder $folder): void {
            $folder->name = Str::squish($folder->name);
            $folder->slug = Str::slug($folder->name) ?: 'folder';
        });
    }

    /** @return BelongsTo<MediaFolder, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<MediaFolder, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    /** @return HasMany<Media, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
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

    /** @param Builder<MediaFolder> $query
     * @return Builder<MediaFolder>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function isDescendantOf(MediaFolder $folder): bool
    {
        $parentId = $this->parent_id;
        $visited = [];

        while ($parentId !== null && ! in_array($parentId, $visited, true)) {
            if ($parentId === $folder->id) {
                return true;
            }

            $visited[] = $parentId;
            $parentId = self::query()->whereKey($parentId)->value('parent_id');
        }

        return false;
    }
}
