<?php

namespace App\Models;

use Database\Factories\EventTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $color
 * @property string|null $icon
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'name',
    'slug',
    'description',
    'color',
    'icon',
    'is_active',
    'sort_order',
])]
class EventType extends Model
{
    /** @use HasFactory<EventTypeFactory> */
    use HasFactory;

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected static function booted(): void
    {
        static::saving(function (EventType $eventType): void {
            if (! filled($eventType->slug)) {
                $eventType->slug = static::uniqueSlug($eventType->name, $eventType->getKey());
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * @param  Builder<EventType>  $query
     * @return Builder<EventType>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function uniqueSlug(string $name, int|string|null $exceptId = null): string
    {
        $base = Str::slug($name) ?: 'event-type';
        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->where('slug', $slug)
            ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
