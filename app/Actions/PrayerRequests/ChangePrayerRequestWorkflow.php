<?php

namespace App\Actions\PrayerRequests;

use App\AccountStatus;
use App\Activity\ActivityLogger;
use App\Models\PrayerRequest;
use App\Models\PrayerRequestUpdate;
use App\Models\User;
use App\Notifications\PrayerRequestAssignedNotification;
use App\PermissionName;
use App\PrayerRequestPriority;
use App\PrayerRequestStatus;
use App\PrayerRequestUpdateType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChangePrayerRequestWorkflow
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function changeStatus(User $actor, PrayerRequest $prayerRequest, PrayerRequestStatus $status): PrayerRequest
    {
        $ability = match ($status) {
            PrayerRequestStatus::Answered => 'markAnswered',
            PrayerRequestStatus::Archived => 'archive',
            default => 'update',
        };
        Gate::forUser($actor)->authorize($ability, $prayerRequest);
        $oldStatus = $prayerRequest->status;
        if ($oldStatus === $status) {
            return $prayerRequest;
        }

        DB::transaction(function () use ($actor, $prayerRequest, $status, $oldStatus): void {
            $attributes = [
                'status' => $status,
                'reviewed_at' => $status !== PrayerRequestStatus::New ? ($prayerRequest->reviewed_at ?? now()) : $prayerRequest->reviewed_at,
                'reviewed_by' => $status !== PrayerRequestStatus::New ? ($prayerRequest->reviewed_by ?? $actor->id) : $prayerRequest->reviewed_by,
                'answered_at' => $status === PrayerRequestStatus::Answered ? ($prayerRequest->answered_at ?? now()) : $prayerRequest->answered_at,
                'closed_at' => $status === PrayerRequestStatus::Closed ? ($prayerRequest->closed_at ?? now()) : $prayerRequest->closed_at,
            ];
            if (in_array($status, [PrayerRequestStatus::Archived, PrayerRequestStatus::Spam], true)) {
                $attributes += ['is_published' => false, 'published_at' => null, 'published_by' => null];
            }
            $prayerRequest->forceFill($attributes)->save();
            $this->timeline($actor, $prayerRequest, PrayerRequestUpdateType::StatusChange, __('Status changed from :old to :new.', ['old' => $oldStatus->label(), 'new' => $status->label()]));
        });

        $this->logChange($actor, $prayerRequest, 'status-changed', ['status' => $oldStatus->value], ['status' => $status->value]);

        return $prayerRequest->refresh();
    }

    public function assign(User $actor, PrayerRequest $prayerRequest, ?User $assignee): PrayerRequest
    {
        Gate::forUser($actor)->authorize('assign', $prayerRequest);
        if ($assignee !== null && ($assignee->accountStatus() !== AccountStatus::Active || ! $assignee->can(PermissionName::PrayerRequestsUpdate))) {
            throw ValidationException::withMessages(['assignedTo' => __('The selected user is not eligible for prayer-request assignment.')]);
        }

        $oldAssignee = $prayerRequest->assigned_to;
        if ($oldAssignee === $assignee?->id) {
            return $prayerRequest;
        }

        DB::transaction(function () use ($actor, $prayerRequest, $assignee): void {
            $prayerRequest->forceFill([
                'assigned_to' => $assignee?->id,
                'status' => $assignee !== null && $prayerRequest->status === PrayerRequestStatus::New ? PrayerRequestStatus::Assigned : $prayerRequest->status,
                'reviewed_at' => $prayerRequest->reviewed_at ?? now(),
                'reviewed_by' => $prayerRequest->reviewed_by ?? $actor->id,
            ])->save();
            $this->timeline($actor, $prayerRequest, PrayerRequestUpdateType::Assignment, $assignee === null ? __('Request unassigned.') : __('Request assigned to :name.', ['name' => $assignee->name]));
        });

        $event = $oldAssignee === null ? 'assigned' : ($assignee === null ? 'unassigned' : 'reassigned');
        $this->logChange($actor, $prayerRequest, $event, ['assigned_to' => $oldAssignee], ['assigned_to' => $assignee?->id]);
        if ($assignee !== null) {
            $assignee->notify((new PrayerRequestAssignedNotification($prayerRequest->reference_number))->afterCommit());
        }

        return $prayerRequest->refresh()->load('assignee:id,name,email');
    }

    public function changePriority(User $actor, PrayerRequest $prayerRequest, PrayerRequestPriority $priority): PrayerRequest
    {
        Gate::forUser($actor)->authorize('update', $prayerRequest);
        $oldPriority = $prayerRequest->priority;
        if ($oldPriority === $priority) {
            return $prayerRequest;
        }

        $prayerRequest->forceFill(['priority' => $priority])->save();
        $this->timeline($actor, $prayerRequest, PrayerRequestUpdateType::StatusChange, __('Priority changed from :old to :new.', ['old' => $oldPriority->label(), 'new' => $priority->label()]));
        $this->logChange($actor, $prayerRequest, 'priority-changed', ['priority' => $oldPriority->value], ['priority' => $priority->value]);

        return $prayerRequest->refresh();
    }

    private function timeline(User $actor, PrayerRequest $prayerRequest, PrayerRequestUpdateType $type, string $note): void
    {
        PrayerRequestUpdate::query()->create(['prayer_request_id' => $prayerRequest->id, 'user_id' => $actor->id, 'type' => $type, 'note' => $note, 'is_private' => true]);
    }

    /** @param array<string, mixed> $old
     * @param  array<string, mixed>  $new
     */
    private function logChange(User $actor, PrayerRequest $prayerRequest, string $event, array $old, array $new): void
    {
        $this->activityLogger->log(
            logName: 'prayer-requests', event: "prayer-request.{$event}",
            description: str($event)->headline()->toString()." prayer request {$prayerRequest->reference_number}.",
            subject: $prayerRequest, causer: $actor, oldValues: $old, newValues: $new,
        );
    }
}
