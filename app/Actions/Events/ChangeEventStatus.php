<?php

namespace App\Actions\Events;

use App\Activity\ActivityLogger;
use App\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ChangeEventStatus
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function publish(User $actor, Event $event): Event
    {
        return $this->change($actor, $event, EventStatus::Published, 'publish');
    }

    public function unpublish(User $actor, Event $event): Event
    {
        return $this->change($actor, $event, EventStatus::Draft, 'publish', 'unpublished');
    }

    public function cancel(User $actor, Event $event): Event
    {
        return $this->change($actor, $event, EventStatus::Cancelled, 'cancel');
    }

    public function complete(User $actor, Event $event): Event
    {
        return $this->change($actor, $event, EventStatus::Completed, 'complete');
    }

    private function change(
        User $actor,
        Event $event,
        EventStatus $status,
        string $ability,
        ?string $eventName = null,
    ): Event {
        Gate::forUser($actor)->authorize($ability, $event);
        $oldStatus = $event->status;
        $event->forceFill([
            'status' => $status,
            'published_at' => $status === EventStatus::Published ? now() : $event->published_at,
            'updated_by' => $actor->id,
        ])->save();

        $eventName ??= $status->value;
        $this->activityLogger->log(
            logName: 'events',
            event: "event.{$eventName}",
            description: str($eventName)->headline()->toString()." event “{$event->title}”.",
            subject: $event,
            causer: $actor,
            oldValues: ['status' => $oldStatus->value],
            newValues: ['status' => $status->value],
        );

        return $event->refresh();
    }
}
