<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactSubmissionAcknowledgement extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $name, public readonly string $referenceNumber, public readonly string $subject, public readonly ?string $publicContactEmail = null) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject(__('We received your message — :reference', ['reference' => $this->referenceNumber]))->greeting(__('Hello :name,', ['name' => $this->name]))->line(__('Thank you for contacting Triumphant World Ministry. Your message has been received.'))->line(__('Reference: :reference', ['reference' => $this->referenceNumber]))->line(__('Subject: :subject', ['subject' => $this->subject]))->line(__('Please do not send sensitive financial or confidential information by email.'));
        if ($this->publicContactEmail !== null) {
            $mail->line(__('Public contact email: :email', ['email' => $this->publicContactEmail]));
        }

        return $mail;
    }
}
