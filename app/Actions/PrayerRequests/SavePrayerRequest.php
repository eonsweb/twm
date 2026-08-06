<?php

namespace App\Actions\PrayerRequests;

use App\Activity\ActivityLogger;
use App\Models\PrayerRequest;
use App\Models\PrayerRequestUpdate;
use App\Models\User;
use App\Notifications\PrayerRequestAssignedNotification;
use App\PrayerRequestPriority;
use App\PrayerRequestSource;
use App\PrayerRequestStatus;
use App\PrayerRequestUpdateType;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SavePrayerRequest
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /** @param array<string, mixed> $data
     * @param  array{ip_address?: string|null, user_agent?: string|null}  $metadata
     */
    public function handle(
        ?User $actor,
        array $data,
        ?PrayerRequest $prayerRequest = null,
        bool $publicSubmission = false,
        array $metadata = [],
    ): PrayerRequest {
        $creating = $prayerRequest === null;
        if (! $publicSubmission) {
            abort_unless($actor instanceof User, 403);
            Gate::forUser($actor)->authorize($creating ? 'create' : 'update', $prayerRequest ?? PrayerRequest::class);
        }

        $prayerRequest ??= new PrayerRequest;
        $oldValues = $creating ? [] : $this->auditValues($prayerRequest);
        $oldAssignee = $prayerRequest->assigned_to;
        $oldStatus = $prayerRequest->status;

        if ($publicSubmission) {
            $data = Arr::only($data, [
                'name', 'email', 'phone', 'country', 'city', 'subject', 'request', 'category',
                'submission_type', 'privacy_level', 'is_anonymous', 'allow_contact', 'allow_publication',
            ]);
            $data += [
                'status' => PrayerRequestStatus::New->value,
                'priority' => PrayerRequestPriority::Normal->value,
                'source' => PrayerRequestSource::Website->value,
                'ip_address' => $metadata['ip_address'] ?? null,
                'user_agent' => $metadata['user_agent'] ?? null,
                'submitted_by' => $actor?->id,
            ];
        } elseif ($creating) {
            $data['submitted_by'] = $actor->id;
        }

        $saved = DB::transaction(function () use ($prayerRequest, $data, $actor, $creating, $oldAssignee, $oldStatus): PrayerRequest {
            $prayerRequest->fill($data)->save();

            if (! $creating && $oldStatus !== $prayerRequest->status) {
                $this->createTimelineUpdate($prayerRequest, $actor, PrayerRequestUpdateType::StatusChange, __('Status changed from :old to :new.', ['old' => $oldStatus->label(), 'new' => $prayerRequest->status->label()]));
            }

            if ($oldAssignee !== $prayerRequest->assigned_to) {
                $this->createTimelineUpdate($prayerRequest, $actor, PrayerRequestUpdateType::Assignment, $prayerRequest->assigned_to === null ? __('Request unassigned.') : __('Request assigned to a prayer-team member.'));
            }

            return $prayerRequest->refresh()->load(['assignee:id,name,email', 'reviewer:id,name', 'submitter:id,name']);
        }, attempts: 3);

        $event = $publicSubmission ? 'prayer-request.submitted' : ($creating ? 'prayer-request.created' : 'prayer-request.updated');
        $newValues = $this->auditValues($saved);
        $keys = $creating ? array_keys($newValues) : collect($newValues)->filter(fn (mixed $value, string $key): bool => ($oldValues[$key] ?? null) !== $value)->keys()->all();
        $this->activityLogger->log(
            logName: 'prayer-requests',
            event: $event,
            description: ($publicSubmission ? 'Submitted' : ($creating ? 'Created' : 'Updated'))." prayer request {$saved->reference_number}.",
            subject: $saved,
            causer: $actor,
            oldValues: Arr::only($oldValues, $keys),
            newValues: Arr::only($newValues, $keys),
            origin: $publicSubmission ? 'public' : 'admin',
        );

        if ($saved->assigned_to !== null && $saved->assigned_to !== $oldAssignee && $saved->assignee !== null) {
            $saved->assignee->notify((new PrayerRequestAssignedNotification($saved->reference_number))->afterCommit());
        }

        return $saved;
    }

    private function createTimelineUpdate(PrayerRequest $prayerRequest, ?User $actor, PrayerRequestUpdateType $type, string $note): void
    {
        PrayerRequestUpdate::query()->create([
            'prayer_request_id' => $prayerRequest->id,
            'user_id' => $actor?->id,
            'type' => $type,
            'note' => $note,
            'is_private' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function auditValues(PrayerRequest $prayerRequest): array
    {
        return [
            'reference_number' => $prayerRequest->reference_number,
            'category' => $prayerRequest->category?->value,
            'privacy_level' => $prayerRequest->privacy_level->value,
            'status' => $prayerRequest->status->value,
            'priority' => $prayerRequest->priority->value,
            'assigned_to' => $prayerRequest->assigned_to,
            'is_anonymous' => $prayerRequest->is_anonymous,
            'allow_contact' => $prayerRequest->allow_contact,
            'allow_publication' => $prayerRequest->allow_publication,
            'is_published' => $prayerRequest->is_published,
            'source' => $prayerRequest->source?->value,
        ];
    }
}
