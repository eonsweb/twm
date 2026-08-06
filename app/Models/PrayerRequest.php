<?php

namespace App\Models;

use App\PrayerRequestCategory;
use App\PrayerRequestPriority;
use App\PrayerRequestPrivacy;
use App\PrayerRequestSource;
use App\PrayerRequestStatus;
use Database\Factories\PrayerRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
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
 * @property string $reference_number
 * @property string $public_token
 * @property string|null $name
 * @property string|null $email
 * @property string|null $phone
 * @property string $subject
 * @property string $request
 * @property PrayerRequestCategory|null $category
 * @property PrayerRequestPrivacy $privacy_level
 * @property PrayerRequestStatus $status
 * @property PrayerRequestPriority $priority
 * @property int|null $assigned_to
 * @property bool $is_anonymous
 * @property bool $allow_contact
 * @property bool $allow_publication
 * @property bool $is_published
 * @property PrayerRequestSource|null $source
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $answered_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $published_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'reference_number', 'public_token', 'name', 'email', 'phone', 'country', 'city',
    'subject', 'request', 'category', 'submission_type', 'privacy_level', 'status',
    'priority', 'assigned_to', 'reviewed_by', 'reviewed_at', 'answered_at',
    'closed_at', 'is_anonymous', 'allow_contact', 'allow_publication', 'is_published',
    'published_at', 'published_by', 'public_title', 'public_excerpt', 'public_content',
    'admin_notes', 'source', 'ip_address', 'user_agent', 'submitted_by',
])]
#[Hidden(['name', 'email', 'phone', 'country', 'city', 'request', 'admin_notes', 'ip_address', 'user_agent'])]
class PrayerRequest extends Model
{
    /** @use HasFactory<PrayerRequestFactory> */
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'submission_type' => 'identified',
        'privacy_level' => 'private',
        'status' => 'new',
        'priority' => 'normal',
        'is_anonymous' => false,
        'allow_contact' => false,
        'allow_publication' => false,
        'is_published' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (PrayerRequest $prayerRequest): void {
            $prayerRequest->public_token ??= (string) Str::ulid();
            $prayerRequest->reference_number ??= 'PR-PENDING-'.Str::uuid();
        });

        static::created(function (PrayerRequest $prayerRequest): void {
            $year = $prayerRequest->created_at->year;
            $prayerRequest->forceFill([
                'reference_number' => sprintf('PR-%d-%06d', $year, $prayerRequest->id),
            ])->saveQuietly();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** @return HasMany<PrayerRequestUpdate, $this> */
    public function updates(): HasMany
    {
        return $this->hasMany(PrayerRequestUpdate::class)->oldest();
    }

    /** @param Builder<PrayerRequest> $query
     * @return Builder<PrayerRequest>
     */
    public function scopeNew(Builder $query): Builder
    {
        return $query->where('status', PrayerRequestStatus::New);
    }

    /** @param Builder<PrayerRequest> $query
     * @return Builder<PrayerRequest>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [PrayerRequestStatus::Closed, PrayerRequestStatus::Archived, PrayerRequestStatus::Spam]);
    }

    /** @param Builder<PrayerRequest> $query
     * @return Builder<PrayerRequest>
     */
    public function scopeUrgent(Builder $query): Builder
    {
        return $query->where('priority', PrayerRequestPriority::Urgent);
    }

    /** @param Builder<PrayerRequest> $query
     * @return Builder<PrayerRequest>
     */
    public function scopeAnswered(Builder $query): Builder
    {
        return $query->where('status', PrayerRequestStatus::Answered);
    }

    /** @param Builder<PrayerRequest> $query
     * @return Builder<PrayerRequest>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->where('allow_publication', true)
            ->where('privacy_level', '!=', PrayerRequestPrivacy::Private)
            ->whereNotNull('public_title')
            ->whereNotNull('public_content')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /** @param Builder<PrayerRequest> $query
     * @return Builder<PrayerRequest>
     */
    public function scopeAssignedTo(Builder $query, User|int $user): Builder
    {
        return $query->where('assigned_to', $user instanceof User ? $user->id : $user);
    }

    /** @param Builder<PrayerRequest> $query
     * @return Builder<PrayerRequest>
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $term = '%'.Str::squish($search).'%';

        return $query->where(fn (Builder $query): Builder => $query
            ->where('reference_number', 'like', $term)
            ->orWhere('name', 'like', $term)
            ->orWhere('email', 'like', $term)
            ->orWhere('phone', 'like', $term)
            ->orWhere('subject', 'like', $term)
            ->orWhere('request', 'like', $term));
    }

    public function requesterLabel(): string
    {
        return $this->is_anonymous || blank($this->name) ? __('Anonymous') : $this->name;
    }

    public function isPubliclyVisible(): bool
    {
        return $this->is_published
            && $this->privacy_level !== PrayerRequestPrivacy::Private
            && $this->allow_publication
            && $this->published_at?->isPast()
            && filled($this->public_title)
            && filled($this->public_content);
    }

    protected function casts(): array
    {
        return [
            'category' => PrayerRequestCategory::class,
            'privacy_level' => PrayerRequestPrivacy::class,
            'status' => PrayerRequestStatus::class,
            'priority' => PrayerRequestPriority::class,
            'source' => PrayerRequestSource::class,
            'reviewed_at' => 'datetime', 'answered_at' => 'datetime', 'closed_at' => 'datetime',
            'is_anonymous' => 'boolean', 'allow_contact' => 'boolean',
            'allow_publication' => 'boolean', 'is_published' => 'boolean', 'published_at' => 'datetime',
        ];
    }
}
