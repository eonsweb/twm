<?php

namespace App\Actions\Ministries;

use App\Activity\ActivityLogger;
use App\Models\Ministry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DeleteMinistry
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function delete(User $actor, Ministry $ministry): void
    {
        Gate::forUser($actor)->authorize('delete', $ministry);
        $ministry->delete();
        $this->log($actor, $ministry, 'deleted');
    }

    public function restore(User $actor, Ministry $ministry): void
    {
        Gate::forUser($actor)->authorize('restore', $ministry);
        $ministry->restore();
        $this->log($actor, $ministry, 'restored');
    }

    public function forceDelete(User $actor, Ministry $ministry): void
    {
        Gate::forUser($actor)->authorize('forceDelete', $ministry);
        $paths = array_filter([$ministry->featured_image, $ministry->logo]);
        DB::transaction(function () use ($ministry): void {
            $ministry->leaders()->detach();
            $ministry->sermons()->detach();
            $ministry->events()->update(['ministry_id' => null]);
            $ministry->forceDelete();
        });
        Storage::disk('public')->delete($paths);
        $this->log($actor, $ministry, 'force-deleted');
    }

    private function log(User $actor, Ministry $ministry, string $action): void
    {
        $this->activityLogger->log(
            logName: 'ministries',
            event: "ministry.{$action}",
            description: str($action)->headline()->toString()." ministry \"{$ministry->name}\".",
            subject: $ministry,
            causer: $actor,
            properties: ['slug' => $ministry->slug],
        );
    }
}
