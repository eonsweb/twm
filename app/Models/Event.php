<?php

namespace App\Models;

use App\EventLocationType;
use App\EventScheduleType;
use App\EventStatus;
use App\Services\EventScheduleService;
use App\Support\EventIcons;
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
 * @property int|null $featured_image_id
 * @property string|null $icon
 * @property EventLocationType|null $location_type
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
 * @property EventScheduleType $schedule_type
 * @property bool $is_all_day
 * @property bool $is_recurring
 * @property string|null $recurrence_rule
 * @property int $recurrence_interval
 * @property list<string>|null $recurrence_days
 * @property string|null $recurrence_week_of_month
 * @property int|null $recurrence_month
 * @property int|null $recurrence_day_of_month
 * @property Carbon|null $recurrence_end_date
 * @property bool $registration_required
 * @property Carbon|null $registration_deadline
 * @property int|null $maximum_attendees
 * @property bool $is_featured
 * @property bool $is_active
 * @property int $sort_order
 * @property bool $is_livestreamed
 * @property EventStatus $status
 * @property Carbon|null $published_at
 * @property Carbon|null $deleted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read EventType|null $eventType
 * @property-read Media|null $featuredImage
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
    'featured_image_id',
    'icon',
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
    'schedule_type',
    'is_all_day',
    'is_recurring',
    'recurrence_rule',
    'recurrence_interval',
    'recurrence_days',
    'recurrence_week_of_month',
    'recurrence_month',
    'recurrence_day_of_month',
    'recurrence_end_date',
    'registration_required',
    'registration_deadline',
    'maximum_attendees',
    'is_featured',
    'is_active',
    'sort_order',
    'is_livestreamed',
    'livestream_url',
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
        'schedule_type' => 'one_time',
        'location_type' => 'physical',
        'status' => 'draft',
        'is_all_day' => false,
        'is_recurring' => false,
        'recurrence_interval' => 1,
        'registration_required' => false,
        'is_featured' => false,
        'is_active' => true,
        'sort_order' => 0,
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

    /** @return BelongsTo<Media, $this> */
    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_id');
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
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
            ->whereIn('status', [EventStatus::Published, EventStatus::Cancelled, EventStatus::Completed, EventStatus::Archived])
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
            $query->where(function (Builder $query): void {
                $query->where('schedule_type', EventScheduleType::OneTime)
                    ->where(function (Builder $query): void {
                        $query->where('starts_at', '>=', now())
                            ->orWhere(function (Builder $query): void {
                                $query->where('starts_at', '<', now())
                                    ->whereNotNull('ends_at')
                                    ->where('ends_at', '>=', now());
                            });
                    });
            })
                ->orWhere(function (Builder $query): void {
                    $query->whereIn('schedule_type', [
                        EventScheduleType::Weekly,
                        EventScheduleType::Monthly,
                        EventScheduleType::Yearly,
                    ])
                        ->where(fn (Builder $query): Builder => $query
                            ->whereNull('recurrence_end_date')
                            ->orWhereDate('recurrence_end_date', '>=', today()));
                })
                ->orWhere(function (Builder $query): void {
                    $query->where('schedule_type', EventScheduleType::Custom)
                        ->where('starts_at', '>=', now());
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
            $query->where('schedule_type', EventScheduleType::OneTime)
                ->where(function (Builder $query): void {
                    $query->whereNotNull('ends_at')->where('ends_at', '<', now())
                        ->orWhere(function (Builder $query): void {
                            $query->whereNull('ends_at')->where('starts_at', '<', now());
                        });
                })
                ->orWhere(function (Builder $query): void {
                    $query->whereIn('schedule_type', [
                        EventScheduleType::Weekly,
                        EventScheduleType::Monthly,
                        EventScheduleType::Yearly,
                    ])
                        ->whereNotNull('recurrence_end_date')
                        ->whereDate('recurrence_end_date', '<', today());
                })
                ->orWhere(function (Builder $query): void {
                    $query->where('schedule_type', EventScheduleType::Custom)
                        ->whereRaw('COALESCE(ends_at, starts_at) < ?', [now()]);
                });
        });
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeRecurring(Builder $query): Builder
    {
        return $query->where('schedule_type', '!=', EventScheduleType::OneTime);
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeOneTime(Builder $query): Builder
    {
        return $query->where('schedule_type', EventScheduleType::OneTime);
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeOfType(Builder $query, EventType|int|string $type): Builder
    {
        if ($type instanceof EventType || is_int($type) || ctype_digit((string) $type)) {
            return $query->where('event_type_id', $type instanceof EventType ? $type->getKey() : (int) $type);
        }

        return $query->whereHas('eventType', fn (Builder $query): Builder => $query->where('slug', $type));
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
        if (! $this->is_active) {
            return false;
        }

        return match ($this->status) {
            EventStatus::Published => $this->published_at === null || $this->published_at->isPast(),
            EventStatus::Cancelled, EventStatus::Completed, EventStatus::Archived => $this->published_at !== null && $this->published_at->isPast(),
            default => false,
        };
    }

    public function temporalState(): string
    {
        if ($this->status === EventStatus::Cancelled) {
            return 'cancelled';
        }

        if (in_array($this->status, [EventStatus::Completed, EventStatus::Archived], true) || $this->isPast()) {
            return 'completed';
        }

        if ($this->nextOccurrence()?->isFuture()) {
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
        if ($this->featuredImage?->publicImageUrl() !== null) {
            return $this->featuredImage->publicImageUrl();
        }

        return $this->featured_image !== null
            ? Storage::disk('public')->url($this->featured_image)
            : null;
    }

    public function isRecurring(): bool
    {
        return $this->schedule_type->isRecurring();
    }

    public function isOneTime(): bool
    {
        return $this->schedule_type === EventScheduleType::OneTime;
    }

    public function isUpcoming(): bool
    {
        return $this->nextOccurrence() !== null;
    }

    public function isPast(): bool
    {
        return $this->nextOccurrence() === null;
    }

    public function nextOccurrence(?Carbon $from = null): ?Carbon
    {
        return (new EventScheduleService)->nextOccurrence($this, $from);
    }

    public function scheduleLabel(): string
    {
        return (new EventScheduleService)->label($this);
    }

    public function effectiveIcon(): string
    {
        if ($this->icon !== null) {
            return $this->icon;
        }

        $eventType = $this->eventType;

        return $eventType instanceof EventType && $eventType->icon !== null
            ? $eventType->icon
            : EventIcons::DEFAULT;
    }

    public function locationLabel(): string
    {
        return match ($this->location_type) {
            EventLocationType::Online => 'Online event',
            EventLocationType::Hybrid => $this->venue_name !== null
                ? "{$this->venue_name} + online"
                : 'Hybrid event',
            EventLocationType::Physical => $this->venue_name ?? $this->city ?? 'Venue to be announced',
            null => $this->venue_name ?? $this->city ?? 'Venue to be announced',
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'location_type' => EventLocationType::class,
            'schedule_type' => EventScheduleType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_all_day' => 'boolean',
            'is_recurring' => 'boolean',
            'recurrence_interval' => 'integer',
            'recurrence_days' => 'array',
            'recurrence_month' => 'integer',
            'recurrence_day_of_month' => 'integer',
            'recurrence_end_date' => 'date',
            'registration_required' => 'boolean',
            'registration_deadline' => 'datetime',
            'maximum_attendees' => 'integer',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'is_livestreamed' => 'boolean',
            'status' => EventStatus::class,
            'published_at' => 'datetime',
        ];
    }
}
