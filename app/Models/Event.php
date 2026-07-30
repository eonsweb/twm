<?php

namespace App\Models;

use App\EventLocationType;
use App\EventStatus;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $event_type_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property string $title
 * @property string $slug
 * @property string|null $short_description
 * @property string|null $description
 * @property string|null $featured_image
 * @property EventLocationType $location_type
 * @property string|null $venue_name
 * @property string|null $address
 * @property string|null $city
 * @property string|null $region
 * @property string $country
 * @property string|null $location_url
 * @property string|null $meeting_url
 * @property string|null $registration_url
 * @property string|null $contact_name
 * @property string|null $contact_phone
 * @property string|null $contact_email
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property string $timezone
 * @property bool $is_all_day
 * @property bool $is_recurring
 * @property string|null $recurrence_rule
 * @property bool $registration_required
 * @property Carbon|null $registration_deadline
 * @property int|null $maximum_attendees
 * @property bool $is_featured
 * @property bool $is_livestreamed
 * @property EventStatus $status
 * @property Carbon|null $published_at
 * @property Carbon|null $deleted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read EventType|null $eventType
 * @property-read User|null $creator
 * @property-read User|null $updater
 */
#[Fillable([
    'event_type_id',
    'ministry_id',
    'created_by',
    'updated_by',
    'title',
    'slug',
    'short_description',
    'description',
    'featured_image',
    'location_type',
    'venue_name',
    'address',
    'city',
    'region',
    'country',
    'location_url',
    'meeting_url',
    'registration_url',
    'contact_name',
    'contact_phone',
    'contact_email',
    'starts_at',
    'ends_at',
    'timezone',
    'is_all_day',
    'is_recurring',
    'recurrence_rule',
    'registration_required',
    'registration_deadline',
    'maximum_attendees',
    'is_featured',
    'is_livestreamed',
    'status',
    'published_at',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'country' => 'Ghana',
        'timezone' => 'Africa/Accra',
        'location_type' => 'physical',
        'status' => 'draft',
        'is_all_day' => false,
        'is_recurring' => false,
        'registration_required' => false,
        'is_featured' => false,
        'is_livestreamed' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (Event $event): void {
            if (! filled($event->slug)) {
                $event->slug = static::uniqueSlug($event->title);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<EventType, $this> */
    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }

    /** @return BelongsTo<Ministry, $this> */
    public function ministry(): BelongsTo
    {
        return $this->belongsTo(Ministry::class);
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
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [EventStatus::Published, EventStatus::Cancelled])
            ->where(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->where('status', EventStatus::Published)->whereNull('published_at');
                })->orWhere('published_at', '<=', now());
            });
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('starts_at', '>=', now())
                ->orWhere(function (Builder $query): void {
                    $query->where('starts_at', '<', now())
                        ->whereNotNull('ends_at')
                        ->where('ends_at', '>=', now());
                });
        });
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopePast(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNotNull('ends_at')->where('ends_at', '<', now())
                ->orWhere(function (Builder $query): void {
                    $query->whereNull('ends_at')->where('starts_at', '<', now());
                });
        });
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', EventStatus::Cancelled);
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeToday(Builder $query): Builder
    {
        $start = now()->startOfDay();
        $end = now()->endOfDay();

        return $query
            ->where('starts_at', '<=', $end)
            ->where(function (Builder $query) use ($start): void {
                $query->where(function (Builder $query) use ($start): void {
                    $query->whereNull('ends_at')->where('starts_at', '>=', $start);
                })->orWhere('ends_at', '>=', $start);
            });
    }

    public function isPubliclyAvailable(): bool
    {
        return match ($this->status) {
            EventStatus::Published => $this->published_at === null || $this->published_at->isPast(),
            EventStatus::Cancelled => $this->published_at !== null && $this->published_at->isPast(),
            default => false,
        };
    }

    public function temporalState(): string
    {
        if ($this->status === EventStatus::Cancelled) {
            return 'cancelled';
        }

        if ($this->status === EventStatus::Completed || $this->eventHasEnded()) {
            return 'completed';
        }

        if ($this->starts_at->isFuture()) {
            return 'upcoming';
        }

        return 'ongoing';
    }

    public function formattedDateRange(): string
    {
        $start = $this->starts_at->setTimezone($this->timezone);
        $end = $this->ends_at?->setTimezone($this->timezone);

        if ($this->is_all_day) {
            if ($end === null || $start->isSameDay($end)) {
                return $start->format('F j, Y').' · All day';
            }

            return $start->format('F j').' – '.$end->format('F j, Y').' · All day';
        }

        if ($end === null) {
            return $start->format('F j, Y \a\t g:i A');
        }

        if ($start->isSameDay($end)) {
            return $start->format('F j, Y \f\r\o\m g:i A').' – '.$end->format('g:i A');
        }

        return $start->format('F j, Y \a\t g:i A').' – '.$end->format('F j, Y \a\t g:i A');
    }

    public function imageUrl(): ?string
    {
        return $this->featured_image !== null
            ? Storage::disk('public')->url($this->featured_image)
            : null;
    }

    public function locationLabel(): string
    {
        return match ($this->location_type) {
            EventLocationType::Online => 'Online event',
            EventLocationType::Hybrid => $this->venue_name !== null
                ? "{$this->venue_name} + online"
                : 'Hybrid event',
            EventLocationType::Physical => $this->venue_name ?? $this->city ?? 'Venue to be announced',
        };
    }

    public static function uniqueSlug(string $title, int|string|null $exceptId = null): string
    {
        $base = Str::slug($title) ?: 'event';
        $slug = $base;
        $suffix = 2;

        while (static::withTrashed()
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->where('slug', $slug)
            ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function eventHasEnded(): bool
    {
        $effectiveEnd = $this->ends_at ?? $this->starts_at;

        return $effectiveEnd->isPast();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'location_type' => EventLocationType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_all_day' => 'boolean',
            'is_recurring' => 'boolean',
            'registration_required' => 'boolean',
            'registration_deadline' => 'datetime',
            'maximum_attendees' => 'integer',
            'is_featured' => 'boolean',
            'is_livestreamed' => 'boolean',
            'status' => EventStatus::class,
            'published_at' => 'datetime',
        ];
    }
}
