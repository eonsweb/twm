<?php

namespace App\Actions\Events;

use App\Activity\ActivityLogger;
use App\Models\EventType;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class SaveEventType
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, array $data, ?EventType $eventType = null): EventType
    {
        $creating = $eventType === null;
        Gate::forUser($actor)->authorize($creating ? 'create' : 'update', $eventType ?? EventType::class);
        $eventType ??= new EventType;
        $oldValues = $creating ? [] : $eventType->only(['name', 'description', 'color', 'icon', 'is_active', 'sort_order']);
        $data['slug'] = EventType::uniqueSlug((string) $data['name'], $eventType->getKey());
        $eventType->fill($data)->save();

        $this->activityLogger->log(
            logName: 'events',
            event: $creating ? 'event_type.created' : 'event_type.updated',
            description: ($creating ? 'Created' : 'Updated')." event type “{$eventType->name}”.",
            subject: $eventType,
            causer: $actor,
            oldValues: $oldValues,
            newValues: $eventType->only(['name', 'description', 'color', 'icon', 'is_active', 'sort_order']),
        );

        return $eventType;
    }

    public function delete(User $actor, EventType $eventType): void
    {
        Gate::forUser($actor)->authorize('delete', $eventType);

        if ($eventType->events()->exists()) {
            $eventType->forceFill(['is_active' => false])->save();
        } else {
            $eventType->delete();
        }

        $this->activityLogger->log(
            logName: 'events',
            event: 'event_type.deleted',
            description: "Deleted or deactivated event type “{$eventType->name}”.",
            subject: $eventType,
            causer: $actor,
        );
    }
}
