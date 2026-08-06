<?php

namespace App\Actions\PrayerRequests;

use App\Activity\ActivityLogger;
use App\Models\PrayerRequest;
use App\Models\PrayerRequestUpdate;
use App\Models\User;
use App\PrayerRequestUpdateType;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AddPrayerRequestUpdate
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $actor, PrayerRequest $prayerRequest, PrayerRequestUpdateType $type, string $note, bool $isPrivate = true): PrayerRequestUpdate
    {
        Gate::forUser($actor)->authorize('addNote', $prayerRequest);
        $note = Str::squish($note);
        if (Str::length($note) < 3 || Str::length($note) > 10000) {
            throw ValidationException::withMessages(['note' => __('The update must be between 3 and 10,000 characters.')]);
        }

        $update = $prayerRequest->updates()->create([
            'user_id' => $actor->id,
            'type' => $type,
            'note' => $note,
            'is_private' => $isPrivate,
        ]);

        $this->activityLogger->log(
            logName: 'prayer-requests',
            event: 'prayer-request.update-added',
            description: "Added a {$type->label()} update to prayer request {$prayerRequest->reference_number}.",
            subject: $prayerRequest,
            causer: $actor,
            properties: ['update_type' => $type->value, 'is_private' => $isPrivate],
        );

        return $update->load('user:id,name');
    }
}
