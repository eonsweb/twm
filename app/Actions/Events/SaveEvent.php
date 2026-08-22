<?php

namespace App\Actions\Events;

use App\Activity\ActivityLogger;
use App\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SaveEvent
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /** @param array<string, mixed> $data */
    public function handle(
        User $actor,
        array $data,
        ?UploadedFile $featuredImage = null,
        bool $removeFeaturedImage = false,
        ?Event $event = null,
    ): Event {
        $creating = $event === null;
        Gate::forUser($actor)->authorize($creating ? 'create' : 'update', $event ?? Event::class);
        $event ??= new Event;
        $this->authorizeWorkflow($actor, $event, $data);
        $oldValues = $creating ? [] : $this->auditValues($event->loadMissing('eventType:id,name'));
        $oldImage = $event->featured_image;
        $newImage = $this->storeImage($featuredImage);

        if ($newImage !== null) {
            $data['featured_image'] = $newImage;
        } elseif ($removeFeaturedImage) {
            $data['featured_image'] = null;
        }

        if ($creating) {
            $data['slug'] = Event::uniqueSlug(filled($data['slug'] ?? null) ? (string) $data['slug'] : (string) $data['title']);
            $data['created_by'] = $actor->id;
        }
        $data['updated_by'] = $actor->id;

        try {
            $savedEvent = DB::transaction(function () use ($event, $data): Event {
                $event->fill($data)->save();

                return $event->refresh()->load(['eventType:id,name', 'creator:id,name', 'updater:id,name']);
            });
        } catch (Throwable $exception) {
            if ($newImage !== null) {
                Storage::disk('public')->delete($newImage);
            }

            throw $exception;
        }

        if (($newImage !== null || $removeFeaturedImage)
            && $oldImage !== null
            && $oldImage !== $savedEvent->featured_image
        ) {
            Storage::disk('public')->delete($oldImage);
        }

        $newValues = $this->auditValues($savedEvent);
        $changedKeys = $creating
            ? array_keys($newValues)
            : collect($newValues)->filter(
                fn (mixed $value, string $key): bool => ($oldValues[$key] ?? null) !== $value,
            )->keys()->all();

        if ($changedKeys !== []) {
            $this->activityLogger->log(
                logName: 'events',
                event: $creating ? 'event.created' : 'event.updated',
                description: ($creating ? 'Created' : 'Updated')." event “{$savedEvent->title}”.",
                subject: $savedEvent,
                causer: $actor,
                oldValues: Arr::only($oldValues, $changedKeys),
                newValues: Arr::only($newValues, $changedKeys),
            );
        }

        return $savedEvent;
    }

    /** @param array<string, mixed> $data */
    private function authorizeWorkflow(User $actor, Event $event, array $data): void
    {
        $newStatus = EventStatus::from((string) $data['status']);
        $oldStatus = $event->exists ? $event->status : EventStatus::Draft;

        if ($newStatus !== $oldStatus) {
            $ability = match ($newStatus) {
                EventStatus::Published, EventStatus::Scheduled => 'publish',
                EventStatus::Cancelled => 'cancel',
                EventStatus::Completed => 'complete',
                EventStatus::Archived => 'complete',
                EventStatus::Draft => in_array($oldStatus, [EventStatus::Published, EventStatus::Scheduled], true)
                    ? 'publish'
                    : null,
            };

            if ($ability !== null) {
                Gate::forUser($actor)->authorize($ability, $event);
            }
        }
    }

    private function storeImage(?UploadedFile $image): ?string
    {
        if ($image === null) {
            return null;
        }

        $path = $image->store('events/featured', 'public');

        if ($path === false) {
            throw new RuntimeException('The event image could not be stored.');
        }

        return $path;
    }

    /** @return array<string, mixed> */
    private function auditValues(Event $event): array
    {
        return [
            'title' => $event->title,
            'slug' => $event->slug,
            'event_type' => $event->eventType?->name,
            'short_description' => str($event->short_description)->limit(300)->toString(),
            'featured_image' => $event->featured_image === null ? 'Not set' : 'Set',
            'featured_image_id' => $event->featured_image_id,
            'icon' => $event->icon,
            'location_type' => $event->location_type?->value,
            'venue_name' => $event->venue_name,
            'starts_at' => $event->starts_at->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'timezone' => $event->timezone,
            'is_all_day' => $event->is_all_day,
            'is_recurring' => $event->is_recurring,
            'schedule_type' => $event->schedule_type->value,
            'recurrence_days' => $event->recurrence_days,
            'recurrence_week_of_month' => $event->recurrence_week_of_month,
            'recurrence_month' => $event->recurrence_month,
            'recurrence_end_date' => $event->recurrence_end_date?->toDateString(),
            'registration_required' => $event->registration_required,
            'status' => $event->status->value,
            'published_at' => $event->published_at?->toIso8601String(),
            'is_featured' => $event->is_featured,
            'is_active' => $event->is_active,
            'sort_order' => $event->sort_order,
            'is_livestreamed' => $event->is_livestreamed,
        ];
    }
}
