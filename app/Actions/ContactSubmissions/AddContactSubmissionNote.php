<?php

namespace App\Actions\ContactSubmissions;

use App\Activity\ActivityLogger;
use App\Models\ContactSubmission;
use App\Models\ContactSubmissionNote;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AddContactSubmissionNote
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $actor, ContactSubmission $submission, string $note): ContactSubmissionNote
    {
        Gate::forUser($actor)->authorize('addNote', $submission);
        $note = trim($note);
        if (mb_strlen($note) < 3 || mb_strlen($note) > 10000) {
            throw ValidationException::withMessages(['note' => __('The note must be between 3 and 10,000 characters.')]);
        }
        $created = $submission->notes()->create(['user_id' => $actor->id, 'note' => $note]);
        $this->activityLogger->log(logName: 'contact-submissions', event: 'contact-submission.note-added', description: "Added an internal note to contact submission {$submission->reference_number}.", causer: $actor, properties: ['submission_id' => $submission->id, 'reference_number' => $submission->reference_number, 'note_id' => $created->id]);

        return $created->load('user:id,name');
    }
}
