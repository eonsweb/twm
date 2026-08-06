<?php

namespace App\Models;

use App\ContactSubmissionCategory;
use App\ContactSubmissionPriority;
use App\ContactSubmissionStatus;
use App\PreferredContactMethod;
use Database\Factories\ContactSubmissionFactory;
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
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $subject
 * @property string $message
 * @property ContactSubmissionCategory $category
 * @property PreferredContactMethod $preferred_contact_method
 * @property ContactSubmissionStatus $status
 * @property ContactSubmissionPriority $priority
 * @property int|null $assigned_to
 * @property int|null $resolved_by
 * @property Carbon|null $admin_replied_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 */
#[Fillable(['reference_number', 'public_token', 'submission_token', 'duplicate_key', 'name', 'email', 'phone', 'subject', 'category', 'message', 'preferred_contact_method', 'status', 'priority', 'assigned_to', 'admin_replied_at', 'resolved_at', 'resolved_by', 'read_at', 'ip_address', 'user_agent', 'source_page'])]
#[Hidden(['email', 'phone', 'message', 'submission_token', 'duplicate_key', 'ip_address', 'user_agent', 'source_page'])]
class ContactSubmission extends Model
{
    /** @use HasFactory<ContactSubmissionFactory> */
    use HasFactory, SoftDeletes;

    protected $attributes = ['status' => 'new', 'priority' => 'normal'];

    protected static function booted(): void
    {
        static::creating(function (ContactSubmission $submission): void {
            $submission->public_token ??= (string) Str::ulid();
            $submission->submission_token ??= (string) Str::uuid();
            $submission->reference_number ??= 'TWM-CON-PENDING-'.Str::random(8);
        });
        static::created(function (ContactSubmission $submission): void {
            $submission->forceFill(['reference_number' => sprintf('TWM-CON-%d-%06d', $submission->created_at->year, $submission->id)])->saveQuietly();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** @return HasMany<ContactSubmissionNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(ContactSubmissionNote::class)->oldest();
    }

    /**
     * @param  Builder<ContactSubmission>  $query
     * @return Builder<ContactSubmission>
     */
    public function scopeNew(Builder $query): Builder
    {
        return $query->where('status', ContactSubmissionStatus::New);
    }

    /**
     * @param  Builder<ContactSubmission>  $query
     * @return Builder<ContactSubmission>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * @param  Builder<ContactSubmission>  $query
     * @return Builder<ContactSubmission>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [ContactSubmissionStatus::Resolved, ContactSubmissionStatus::Closed, ContactSubmissionStatus::Spam]);
    }

    /**
     * @param  Builder<ContactSubmission>  $query
     * @return Builder<ContactSubmission>
     */
    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', ContactSubmissionStatus::Resolved);
    }

    /**
     * @param  Builder<ContactSubmission>  $query
     * @return Builder<ContactSubmission>
     */
    public function scopeSpam(Builder $query): Builder
    {
        return $query->where('status', ContactSubmissionStatus::Spam);
    }

    /**
     * @param  Builder<ContactSubmission>  $query
     * @return Builder<ContactSubmission>
     */
    public function scopeAssignedTo(Builder $query, User|int $user): Builder
    {
        return $query->where('assigned_to', $user instanceof User ? $user->id : $user);
    }

    /**
     * @param  Builder<ContactSubmission>  $query
     * @return Builder<ContactSubmission>
     */
    public function scopeCategory(Builder $query, ContactSubmissionCategory|string $category): Builder
    {
        return $query->where('category', $category instanceof ContactSubmissionCategory ? $category->value : $category);
    }

    /**
     * @param  Builder<ContactSubmission>  $query
     * @return Builder<ContactSubmission>
     */
    public function scopePriority(Builder $query, ContactSubmissionPriority|string $priority): Builder
    {
        return $query->where('priority', $priority instanceof ContactSubmissionPriority ? $priority->value : $priority);
    }

    /**
     * @param  Builder<ContactSubmission>  $query
     * @return Builder<ContactSubmission>
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $term = '%'.Str::squish($search).'%';

        return $query->where(fn (Builder $query): Builder => $query->where('reference_number', 'like', $term)->orWhere('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term)->orWhere('subject', 'like', $term)->orWhere('message', 'like', $term));
    }

    public function replySubject(): string
    {
        return Str::limit("Re: [{$this->reference_number}] ".Str::squish($this->subject), 200, '');
    }

    protected function casts(): array
    {
        return ['category' => ContactSubmissionCategory::class, 'preferred_contact_method' => PreferredContactMethod::class, 'status' => ContactSubmissionStatus::class, 'priority' => ContactSubmissionPriority::class, 'admin_replied_at' => 'datetime', 'resolved_at' => 'datetime', 'read_at' => 'datetime'];
    }
}
