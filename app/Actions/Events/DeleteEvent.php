<?php

namespace App\Actions\Events;

use App\Activity\ActivityLogger;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

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

    public function forceDelete(User $actor, Event $event): void
    {
        $event = Event::onlyTrashed()->findOrFail($event->getKey());
        Gate::forUser($actor)->authorize('forceDelete', $event);
        $ownedImage = $event->featured_image;

        $event->forceDelete();

        if ($ownedImage !== null) {
            Storage::disk('public')->delete($ownedImage);
        }

        $this->log($actor, $event, 'force-deleted');
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
