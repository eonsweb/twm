<?php

namespace App\Actions\Sermons;

use App\Activity\ActivityLogger;
use App\Models\Sermon;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DeleteSermon
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function delete(User $actor, Sermon $sermon): void
    {
        Gate::forUser($actor)->authorize('delete', $sermon);
        $sermon->delete();

        $this->log($actor, $sermon, 'deleted', 'Deleted');
    }

    public function restore(User $actor, Sermon $sermon): Sermon
    {
        Gate::forUser($actor)->authorize('restore', $sermon);
        $sermon->restore();
        $this->log($actor, $sermon, 'restored', 'Restored');

        return $sermon->refresh();
    }

    public function forceDelete(User $actor, Sermon $sermon): void
    {
        Gate::forUser($actor)->authorize('forceDelete', $sermon);
        $thumbnailPath = $sermon->thumbnail_path;
        $this->log($actor, $sermon, 'force_deleted', 'Permanently deleted');
        $sermon->forceDelete();

        if ($thumbnailPath !== null) {
            Storage::disk('public')->delete($thumbnailPath);
        }
    }

    private function log(User $actor, Sermon $sermon, string $event, string $verb): void
    {
        $this->activityLogger->log(
            logName: 'sermons',
            event: 'sermon.'.$event,
            description: "{$verb} sermon “{$sermon->title}”.",
            subject: $sermon,
            causer: $actor,
            properties: ['slug' => $sermon->slug],
        );
    }
}
