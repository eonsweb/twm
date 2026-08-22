<?php

namespace App\Actions\Events;

use App\Activity\ActivityLogger;
use App\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DuplicateEvent
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $actor, Event $source): Event
    {
        Gate::forUser($actor)->authorize('duplicate', $source);
        $copy = $source->replicate([
            'slug',
            'featured_image',
            'featured_image_id',
            'published_at',
            'created_by',
            'updated_by',
        ]);
        $copy->forceFill([
            'title' => $source->title.' (Copy)',
            'slug' => Event::uniqueSlug($source->title.' copy'),
            'featured_image' => null,
            'featured_image_id' => null,
            'status' => EventStatus::Draft,
            'published_at' => null,
            'is_featured' => false,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ])->save();

        $this->activityLogger->log(
            logName: 'events',
            event: 'event.duplicated',
            description: "Duplicated event “{$source->title}”.",
            subject: $copy,
            causer: $actor,
            properties: ['source_event_id' => $source->id],
        );

        return $copy;
    }
}
