<?php

namespace App\Actions\ContactSubmissions;

use App\Activity\ActivityLogger;
use App\ContactSubmissionStatus;
use App\Models\ContactSubmission;
use App\Notifications\ContactSubmissionAcknowledgement;
use App\Notifications\NewContactSubmissionNotification;
use App\Settings\SettingManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

class SubmitContactSubmission
{
    public function __construct(private readonly ActivityLogger $activityLogger, private readonly SettingManager $settings) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array{ip_address?: string|null, user_agent?: string|null, source_page?: string|null}  $metadata
     */
    public function handle(array $data, string $submissionToken, array $metadata = []): ContactSubmission
    {
        $duplicateKey = hash('sha256', Str::lower((string) $data['email']).'|'.Str::squish((string) $data['subject']).'|'.trim((string) $data['message']).'|'.intdiv((int) now()->timestamp, 600));
        $existing = ContactSubmission::query()->where('submission_token', $submissionToken)->orWhere('duplicate_key', $duplicateKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        try {
            $submission = ContactSubmission::query()->create([...$data, 'submission_token' => $submissionToken, 'duplicate_key' => $duplicateKey, 'status' => ContactSubmissionStatus::New, 'ip_address' => $metadata['ip_address'] ?? null, 'user_agent' => $metadata['user_agent'] ?? null, 'source_page' => $metadata['source_page'] ?? null]);
        } catch (QueryException $exception) {
            $submission = ContactSubmission::query()->where('submission_token', $submissionToken)->orWhere('duplicate_key', $duplicateKey)->first();
            if ($submission === null) {
                throw $exception;
            }

            return $submission;
        }

        $this->activityLogger->log(logName: 'contact-submissions', event: 'contact-submission.created', description: "Created contact submission {$submission->reference_number}.", properties: ['submission_id' => $submission->id, 'reference_number' => $submission->reference_number, 'category' => $submission->category->value], origin: 'public');
        $this->sendNotifications($submission);

        return $submission;
    }

    private function sendNotifications(ContactSubmission $submission): void
    {
        if (! $this->settings->get('email', 'notifications_enabled', true)) {
            return;
        }

        try {
            $publicContactEmail = $this->settings->get('contact', 'primary_email');
            Notification::route('mail', $submission->email)->notify((new ContactSubmissionAcknowledgement($submission->name, $submission->reference_number, $submission->subject, is_string($publicContactEmail) && $publicContactEmail !== '' ? $publicContactEmail : null))->afterCommit());
            if ($this->settings->get('email', 'contact_notifications', true)) {
                foreach ($this->notificationRecipients() as $recipient) {
                    Notification::route('mail', $recipient)->notify((new NewContactSubmissionNotification($submission->id, $submission->reference_number, $submission->name, $submission->email, $submission->phone, $submission->category->label(), $submission->subject, Str::limit($submission->message, 500), $submission->created_at->toDayDateTimeString()))->afterCommit());
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Contact submission notifications could not be queued.', ['submission_id' => $submission->id, 'exception' => $exception::class]);
        }
    }

    /** @return list<string> */
    private function notificationRecipients(): array
    {
        $recipients = preg_split('/\R/', (string) $this->settings->get('email', 'notification_recipients', '')) ?: [];

        return array_values(collect($recipients)->map(fn (string $email): string => Str::lower(Str::squish($email)))->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)->unique()->all());
    }
}
