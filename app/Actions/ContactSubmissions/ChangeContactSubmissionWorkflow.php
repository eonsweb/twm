<?php

namespace App\Actions\ContactSubmissions;

use App\AccountStatus;
use App\Activity\ActivityLogger;
use App\ContactSubmissionPriority;
use App\ContactSubmissionStatus;
use App\Models\ContactSubmission;
use App\Models\User;
use App\PermissionName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChangeContactSubmissionWorkflow
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function markRead(User $actor, ContactSubmission $submission): ContactSubmission
    {
        Gate::forUser($actor)->authorize('view', $submission);
        if ($submission->read_at !== null) {
            return $submission;
        }
        $submission->forceFill(['read_at' => now()])->save();
        $this->log($actor, $submission, 'viewed-first-time', [], ['read_at' => $submission->read_at?->toISOString()]);

        return $submission->refresh();
    }

    public function markUnread(User $actor, ContactSubmission $submission): ContactSubmission
    {
        Gate::forUser($actor)->authorize('update', $submission);
        $old = $submission->read_at?->toISOString();
        $submission->forceFill(['read_at' => null])->save();
        $this->log($actor, $submission, 'marked-unread', ['read_at' => $old], ['read_at' => null]);

        return $submission->refresh();
    }

    public function changeStatus(User $actor, ContactSubmission $submission, ContactSubmissionStatus $status): ContactSubmission
    {
        $ability = match ($status) {
            ContactSubmissionStatus::Resolved => 'resolve', ContactSubmissionStatus::Spam => 'markSpam', default => 'update'
        };
        Gate::forUser($actor)->authorize($ability, $submission);
        $old = $submission->status;
        if ($old === $status) {
            return $submission;
        }
        DB::transaction(function () use ($submission, $status, $actor): void {
            $submission->forceFill(['status' => $status, 'resolved_at' => $status === ContactSubmissionStatus::Resolved ? now() : null, 'resolved_by' => $status === ContactSubmissionStatus::Resolved ? $actor->id : null, 'read_at' => $submission->read_at ?? now()])->save();
        });
        $this->log($actor, $submission, $status === ContactSubmissionStatus::Resolved ? 'resolved' : ($old === ContactSubmissionStatus::Resolved ? 'reopened' : 'status-changed'), ['status' => $old->value], ['status' => $status->value]);

        return $submission->refresh();
    }

    public function changePriority(User $actor, ContactSubmission $submission, ContactSubmissionPriority $priority): ContactSubmission
    {
        Gate::forUser($actor)->authorize('update', $submission);
        $old = $submission->priority;
        if ($old === $priority) {
            return $submission;
        }
        $submission->forceFill(['priority' => $priority])->save();
        $this->log($actor, $submission, 'priority-changed', ['priority' => $old->value], ['priority' => $priority->value]);

        return $submission->refresh();
    }

    public function assign(User $actor, ContactSubmission $submission, ?User $assignee): ContactSubmission
    {
        Gate::forUser($actor)->authorize('assign', $submission);
        if ($assignee !== null && ($assignee->accountStatus() !== AccountStatus::Active || ! $assignee->can(PermissionName::ContactSubmissionsUpdate))) {
            throw ValidationException::withMessages(['assignedTo' => __('Select an active administrator who can manage contact submissions.')]);
        }
        $old = $submission->assigned_to;
        if ($old === $assignee?->id) {
            return $submission;
        }
        $submission->forceFill(['assigned_to' => $assignee?->id])->save();
        $this->log($actor, $submission, 'assigned', ['assigned_to' => $old], ['assigned_to' => $assignee?->id]);

        return $submission->refresh()->load('assignedUser:id,name,email');
    }

    public function recordReply(User $actor, ContactSubmission $submission): ContactSubmission
    {
        Gate::forUser($actor)->authorize('reply', $submission);
        $old = $submission->admin_replied_at?->toISOString();
        $submission->forceFill(['admin_replied_at' => now(), 'status' => ContactSubmissionStatus::WaitingForVisitor, 'read_at' => $submission->read_at ?? now()])->save();
        $this->log($actor, $submission, 'reply-recorded', ['admin_replied_at' => $old], ['admin_replied_at' => $submission->admin_replied_at?->toISOString()]);

        return $submission->refresh();
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function log(User $actor, ContactSubmission $submission, string $event, array $old, array $new): void
    {
        $this->activityLogger->log(logName: 'contact-submissions', event: "contact-submission.{$event}", description: str($event)->headline()->toString()." contact submission {$submission->reference_number}.", causer: $actor, properties: ['submission_id' => $submission->id, 'reference_number' => $submission->reference_number], oldValues: $old, newValues: $new);
    }
}
