<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewContactSubmissionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $submissionId, public readonly string $referenceNumber, public readonly string $name, public readonly string $email, public readonly ?string $phone, public readonly string $category, public readonly string $subject, public readonly string $summary, public readonly string $submittedAt) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject(__('New contact enquiry — :reference', ['reference' => $this->referenceNumber]))->line(__('Reference: :reference', ['reference' => $this->referenceNumber]))->line(__('Visitor: :name (:email)', ['name' => $this->name, 'email' => $this->email]));
        if ($this->phone !== null) {
            $mail->line(__('Phone: :phone', ['phone' => $this->phone]));
        }

        return $mail->line(__('Category: :category', ['category' => $this->category]))->line(__('Subject: :subject', ['subject' => $this->subject]))->line(__('Summary: :summary', ['summary' => $this->summary]))->line(__('Received: :date', ['date' => $this->submittedAt]))->action(__('Review securely'), route('contact-submissions.show', $this->submissionId));
    }
}
