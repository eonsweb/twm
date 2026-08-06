<?php

namespace App\Actions\PrayerRequests;

use App\Activity\ActivityLogger;
use App\Models\PrayerRequest;
use App\Models\User;
use App\PrayerRequestStatus;
use Illuminate\Support\Facades\Gate;

class DeletePrayerRequest
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function delete(User $actor, PrayerRequest $prayerRequest): void
    {
        Gate::forUser($actor)->authorize('delete', $prayerRequest);
        $prayerRequest->forceFill(['is_published' => false, 'published_at' => null, 'published_by' => null])->save();
        $prayerRequest->delete();
        $this->log($actor, $prayerRequest, 'deleted');
    }

    public function restore(User $actor, PrayerRequest $prayerRequest): PrayerRequest
    {
        Gate::forUser($actor)->authorize('restore', $prayerRequest);
        $prayerRequest->restore();
        $prayerRequest->forceFill(['status' => PrayerRequestStatus::UnderReview, 'is_published' => false])->save();
        $this->log($actor, $prayerRequest, 'restored');

        return $prayerRequest->refresh();
    }

    public function forceDelete(User $actor, PrayerRequest $prayerRequest): void
    {
        Gate::forUser($actor)->authorize('forceDelete', $prayerRequest);
        $reference = $prayerRequest->reference_number;
        $prayerRequest->forceDelete();
        $this->activityLogger->log(logName: 'prayer-requests', event: 'prayer-request.force-deleted', description: "Permanently deleted prayer request {$reference}.", causer: $actor);
    }

    private function log(User $actor, PrayerRequest $prayerRequest, string $event): void
    {
        $this->activityLogger->log(logName: 'prayer-requests', event: "prayer-request.{$event}", description: str($event)->headline()->toString()." prayer request {$prayerRequest->reference_number}.", subject: $prayerRequest, causer: $actor);
    }
}
