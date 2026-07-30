<?php

namespace App\Actions\Ministries;

use App\Activity\ActivityLogger;
use App\MinistryStatus;
use App\Models\Ministry;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ChangeMinistryStatus
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function publish(User $actor, Ministry $ministry): Ministry
    {
        return $this->change($actor, $ministry, MinistryStatus::Published, 'published');
    }

    public function draft(User $actor, Ministry $ministry): Ministry
    {
        return $this->change($actor, $ministry, MinistryStatus::Draft, 'moved-to-draft');
    }

    public function inactive(User $actor, Ministry $ministry): Ministry
    {
        return $this->change($actor, $ministry, MinistryStatus::Inactive, 'marked-inactive');
    }

    public function toggleFeatured(User $actor, Ministry $ministry): Ministry
    {
        Gate::forUser($actor)->authorize('feature', $ministry);
        $oldValue = $ministry->is_featured;
        $ministry->forceFill(['is_featured' => ! $oldValue, 'updated_by' => $actor->id])->save();
        $event = $ministry->is_featured ? 'featured' : 'unfeatured';
        $this->activityLogger->log(
            logName: 'ministries',
            event: "ministry.{$event}",
            description: str($event)->headline()->toString()." ministry \"{$ministry->name}\".",
            subject: $ministry,
            causer: $actor,
            oldValues: ['is_featured' => $oldValue],
            newValues: ['is_featured' => $ministry->is_featured],
        );

        return $ministry->refresh();
    }

    private function change(User $actor, Ministry $ministry, MinistryStatus $status, string $event): Ministry
    {
        Gate::forUser($actor)->authorize('publish', $ministry);
        $oldStatus = $ministry->status;
        $ministry->forceFill([
            'status' => $status,
            'published_at' => $status === MinistryStatus::Published && $ministry->published_at === null
                ? now()
                : $ministry->published_at,
            'updated_by' => $actor->id,
        ])->save();
        $this->activityLogger->log(
            logName: 'ministries',
            event: "ministry.{$event}",
            description: str($event)->headline()->toString()." ministry \"{$ministry->name}\".",
            subject: $ministry,
            causer: $actor,
            oldValues: ['status' => $oldStatus->value],
            newValues: ['status' => $status->value],
        );

        return $ministry->refresh();
    }
}
