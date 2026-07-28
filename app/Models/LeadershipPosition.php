<?php

namespace App\Models;

use App\Concerns\LogsActivity;
use Database\Factories\LeadershipPositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'slug',
    'description',
    'rank',
    'is_active',
])]
class LeadershipPosition extends Model
{
    /** @use HasFactory<LeadershipPositionFactory> */
    use HasFactory, LogsActivity;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'rank' => 0,
        'is_active' => true,
    ];

    /**
     * @return HasMany<LeadershipAssignment, $this>
     */
    public function leadershipAssignments(): HasMany
    {
        return $this->hasMany(LeadershipAssignment::class);
    }

    /**
     * @return BelongsToMany<Person, $this>
     */
    public function people(): BelongsToMany
    {
        return $this->belongsToMany(
            Person::class,
            (new LeadershipAssignment)->getTable(),
        )->withPivot([
            'display_title',
            'started_at',
            'ended_at',
            'is_current',
            'is_primary',
            'sort_order',
        ])->withTimestamps();
    }

    /**
     * @param  Builder<LeadershipPosition>  $query
     * @return Builder<LeadershipPosition>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected function activityLogName(): string
    {
        return 'leadership';
    }

    protected function activityDescription(string $event): string
    {
        return Str::headline($event)." leadership position {$this->name}.";
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
