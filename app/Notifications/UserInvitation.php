<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;

class UserInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        if (! $notifiable instanceof User) {
            throw new InvalidArgumentException('User invitations can only be sent to users.');
        }

        return (new MailMessage)
            ->subject(__('Your Triumphant World Ministry administrator account'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('An administrator account has been created for you. Use the button below to choose a secure password.'))
            ->action(__('Set up my account'), route('password.reset', [
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]))
            ->line(__('After setting your password, sign in and verify your email address to access the administration area.'))
            ->line(__('If you were not expecting this invitation, you may ignore this email.'));
    }
}
