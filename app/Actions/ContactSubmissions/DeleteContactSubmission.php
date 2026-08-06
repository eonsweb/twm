<?php

namespace App\Actions\ContactSubmissions;

use App\Activity\ActivityLogger;
use App\ContactSubmissionStatus;
use App\Models\ContactSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteContactSubmission
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function delete(User $actor, ContactSubmission $submission): void
    {
        Gate::forUser($actor)->authorize('delete', $submission);
        $submission->delete();
        $this->log($actor, $submission, 'deleted');
    }

    public function restore(User $actor, ContactSubmission $submission): ContactSubmission
    {
        Gate::forUser($actor)->authorize('restore', $submission);
        $submission->restore();
        if ($submission->status === ContactSubmissionStatus::Spam) {
            $submission->forceFill(['status' => ContactSubmissionStatus::Read])->save();
        } $this->log($actor, $submission, 'restored');

        return $submission->refresh();
    }

    public function forceDelete(User $actor, ContactSubmission $submission): void
    {
        Gate::forUser($actor)->authorize('forceDelete', $submission);
        $reference = $submission->reference_number;
        $id = $submission->id;
        $submission->forceDelete();
        $this->activityLogger->log(logName: 'contact-submissions', event: 'contact-submission.force-deleted', description: "Permanently deleted contact submission {$reference}.", causer: $actor, properties: ['submission_id' => $id, 'reference_number' => $reference]);
    }

    private function log(User $actor, ContactSubmission $submission, string $event): void
    {
        $this->activityLogger->log(logName: 'contact-submissions', event: "contact-submission.{$event}", description: str($event)->headline()->toString()." contact submission {$submission->reference_number}.", causer: $actor, properties: ['submission_id' => $submission->id, 'reference_number' => $submission->reference_number]);
    }
}
