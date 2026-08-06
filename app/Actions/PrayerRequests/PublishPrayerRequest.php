<?php

namespace App\Actions\PrayerRequests;

use App\Activity\ActivityLogger;
use App\Models\PrayerRequest;
use App\Models\PrayerRequestUpdate;
use App\Models\User;
use App\PrayerRequestPrivacy;
use App\PrayerRequestUpdateType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PublishPrayerRequest
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /** @param array{public_title: string|null, public_excerpt: string|null, public_content: string|null} $publicCopy */
    public function publish(User $actor, PrayerRequest $prayerRequest, array $publicCopy): PrayerRequest
    {
        Gate::forUser($actor)->authorize('publish', $prayerRequest);
        if (! $prayerRequest->allow_publication || $prayerRequest->privacy_level === PrayerRequestPrivacy::Private) {
            throw ValidationException::withMessages(['publication' => __('Publication requires explicit consent and a non-private privacy level.')]);
        }
        if (! filled($publicCopy['public_title']) || ! filled($publicCopy['public_content'])) {
            throw ValidationException::withMessages(['publication' => __('A separate public title and public-safe content are required.')]);
        }

        DB::transaction(function () use ($actor, $prayerRequest, $publicCopy): void {
            $prayerRequest->forceFill([
                ...$publicCopy,
                'is_published' => true,
                'published_at' => now(),
                'published_by' => $actor->id,
            ])->save();
            PrayerRequestUpdate::query()->create([
                'prayer_request_id' => $prayerRequest->id, 'user_id' => $actor->id,
                'type' => PrayerRequestUpdateType::Testimony, 'note' => __('Approved a separate public-safe version for publication.'), 'is_private' => true,
            ]);
        });

        $this->log($actor, $prayerRequest, 'published');

        return $prayerRequest->refresh();
    }

    public function unpublish(User $actor, PrayerRequest $prayerRequest): PrayerRequest
    {
        Gate::forUser($actor)->authorize('publish', $prayerRequest);
        $prayerRequest->forceFill(['is_published' => false, 'published_at' => null, 'published_by' => null])->save();
        $this->log($actor, $prayerRequest, 'unpublished');

        return $prayerRequest->refresh();
    }

    private function log(User $actor, PrayerRequest $prayerRequest, string $event): void
    {
        $this->activityLogger->log(
            logName: 'prayer-requests', event: "prayer-request.{$event}",
            description: str($event)->headline()->toString()." prayer request {$prayerRequest->reference_number}.",
            subject: $prayerRequest, causer: $actor,
        );
    }
}
