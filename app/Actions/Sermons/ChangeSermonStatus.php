<?php

namespace App\Actions\Sermons;

use App\Activity\ActivityLogger;
use App\Models\Sermon;
use App\Models\User;
use App\SermonStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChangeSermonStatus
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function publish(User $actor, Sermon $sermon): Sermon
    {
        return $this->change($actor, $sermon, SermonStatus::Published, 'publish');
    }

    public function unpublish(User $actor, Sermon $sermon): Sermon
    {
        return $this->change($actor, $sermon, SermonStatus::Unpublished, 'unpublish');
    }

    public function archive(User $actor, Sermon $sermon): Sermon
    {
        return $this->change($actor, $sermon, SermonStatus::Archived, 'archive');
    }

    public function schedule(User $actor, Sermon $sermon, CarbonInterface $scheduledAt): Sermon
    {
        if (! $scheduledAt->isFuture()) {
            throw ValidationException::withMessages(['scheduledAt' => 'The scheduled time must be in the future.']);
        }

        return $this->change($actor, $sermon, SermonStatus::Scheduled, 'schedule', $scheduledAt);
    }

    public function feature(User $actor, Sermon $sermon, bool $featured): Sermon
    {
        Gate::forUser($actor)->authorize('feature', $sermon);
        $oldValue = $sermon->is_featured;
        $sermon->update(['is_featured' => $featured, 'updated_by' => $actor->id]);

        if ($oldValue !== $featured) {
            $this->activityLogger->log(
                logName: 'sermons',
                event: $featured ? 'sermon.featured' : 'sermon.unfeatured',
                description: ($featured ? 'Featured' : 'Unfeatured')." sermon “{$sermon->title}”.",
                subject: $sermon,
                causer: $actor,
                oldValues: ['is_featured' => $oldValue],
                newValues: ['is_featured' => $featured],
            );
        }

        return $sermon->refresh();
    }

    private function change(
        User $actor,
        Sermon $sermon,
        SermonStatus $status,
        string $ability,
        ?CarbonInterface $scheduledAt = null,
    ): Sermon {
        Gate::forUser($actor)->authorize($ability, $sermon);
        $oldStatus = $sermon->status;
        $updates = [
            'status' => $status,
            'updated_by' => $actor->id,
            'scheduled_at' => $status === SermonStatus::Scheduled ? $scheduledAt : null,
        ];

        if ($status === SermonStatus::Published) {
            $updates['published_at'] = $sermon->published_at ?? now();
        }

        $sermon->update($updates);

        $this->activityLogger->log(
            logName: 'sermons',
            event: 'sermon.'.$status->value,
            description: str($status->label())." sermon “{$sermon->title}”.",
            subject: $sermon,
            causer: $actor,
            oldValues: ['status' => $oldStatus->value],
            newValues: [
                'status' => $status->value,
                'published_at' => $sermon->published_at?->toIso8601String(),
                'scheduled_at' => $sermon->scheduled_at?->toIso8601String(),
            ],
        );

        return $sermon->refresh();
    }
}
