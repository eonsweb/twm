<?php

namespace App\Models;

use App\Concerns\LogsActivity;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $title
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property string $slug
 * @property string|null $photo_path
 * @property string|null $short_bio
 * @property string|null $biography
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $website_url
 * @property string|null $facebook_url
 * @property string|null $instagram_url
 * @property string|null $youtube_url
 * @property bool $is_active
 * @property bool $is_public
 * @property-read string $full_name
 * @property-read Pivot|null $pivot
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'title',
    'first_name',
    'middle_name',
    'last_name',
    'slug',
    'photo_path',
    'short_bio',
    'biography',
    'email',
    'phone',
    'website_url',
    'facebook_url',
    'instagram_url',
    'youtube_url',
    'is_active',
    'is_public',
])]
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory, LogsActivity;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'is_public' => true,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<LeadershipAssignment, $this>
     */
    public function leadershipAssignments(): HasMany
    {
        return $this->hasMany(LeadershipAssignment::class);
    }

    /**
     * @return HasMany<LeadershipAssignment, $this>
     */
    public function currentLeadershipAssignments(): HasMany
    {
        return $this->leadershipAssignments()
            ->where('is_current', true)
            ->orderBy('sort_order');
    }

    /**
     * @return HasOne<LeadershipAssignment, $this>
     */
    public function primaryLeadershipAssignment(): HasOne
    {
        return $this->hasOne(LeadershipAssignment::class)
            ->where('is_current', true)
            ->where('is_primary', true);
    }

    /**
     * @return BelongsToMany<LeadershipPosition, $this>
     */
    public function leadershipPositions(): BelongsToMany
    {
        return $this->belongsToMany(
            LeadershipPosition::class,
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
     * @return HasMany<Sermon, $this>
     */
    public function sermons(): HasMany
    {
        return $this->hasMany(Sermon::class, 'speaker_id');
    }

    /** @return BelongsToMany<Ministry, $this> */
    public function ministries(): BelongsToMany
    {
        return $this->belongsToMany(Ministry::class)
            ->withPivot(['role_title', 'is_primary', 'display_order'])
            ->withTimestamps();
    }

    /**
     * @param  Builder<Person>  $query
     * @return Builder<Person>
     */
    public function scopeLeaders(Builder $query): Builder
    {
        return $query->whereHas('leadershipAssignments');
    }

    /**
     * @param  Builder<Person>  $query
     * @return Builder<Person>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Person>  $query
     * @return Builder<Person>
     */
    public function scopePublicSpeakers(Builder $query): Builder
    {
        return $query
            ->active()
            ->where('is_public', true)
            ->whereHas(
                'sermons',
                fn (Builder $sermons): Builder => $sermons->whereIn(
                    (new Sermon)->qualifyColumn('id'),
                    Sermon::query()->publiclyAvailable()->select('id'),
                ),
            );
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path === null ? null : Storage::disk('public')->url($this->photo_path);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => collect([
            $this->title,
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ])->filter()->implode(' '));
    }

    protected function activityLogName(): string
    {
        return 'leadership';
    }

    protected function activitySubjectName(): string
    {
        return $this->full_name;
    }

    protected function activityDescription(string $event): string
    {
        return match ($event) {
            'created' => "Created leadership profile for {$this->full_name}.",
            'updated' => "Updated leadership profile for {$this->full_name}.",
            'deleted' => "Deleted leadership profile for {$this->full_name}.",
            'restored' => "Restored leadership profile for {$this->full_name}.",
            default => "Changed leadership profile for {$this->full_name}.",
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ];
    }
}
