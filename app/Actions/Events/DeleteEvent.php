<?php

namespace App\Actions\Events;

use App\Activity\ActivityLogger;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteEvent
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function delete(User $actor, Event $event): void
    {
        Gate::forUser($actor)->authorize('delete', $event);
        $event->delete();
        $this->log($actor, $event, 'deleted');
    }

    public function restore(User $actor, Event $event): void
    {
        Gate::forUser($actor)->authorize('restore', $event);
        $event->restore();
        $this->log($actor, $event, 'restored');
    }

    private function log(User $actor, Event $event, string $action): void
    {
        $this->activityLogger->log(
            logName: 'events',
            event: "event.{$action}",
            description: str($action)->headline()->toString()." event “{$event->title}”.",
            subject: $event,
            causer: $actor,
            properties: ['slug' => $event->slug],
        );
    }
}
