<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;

class PrayerRequestAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $referenceNumber) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if (! $notifiable instanceof User) {
            throw new InvalidArgumentException('Prayer request assignment notifications can only be sent to users.');
        }

        return (new MailMessage)
            ->subject(__('A prayer request has been assigned to you'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Prayer request :reference has been assigned to you.', ['reference' => $this->referenceNumber]))
            ->line(__('For confidentiality, request details are available only in the secure administration area.'))
            ->action(__('Review assigned request'), route('prayer-requests.index', ['search' => $this->referenceNumber]));
    }
}
