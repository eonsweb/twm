<?php

namespace App\Listeners;

use App\Activity\ActivityLogger;
use App\Models\User;
use Illuminate\Auth\Events\Login;

class RecordSuccessfulLogin
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);

        $this->logger->log(
            logName: 'authentication',
            event: 'login.succeeded',
            description: "Logged in {$event->user->name}.",
            subject: $event->user,
            causer: $event->user,
            properties: [
                'identifier_type' => filter_var(request()->input('login'), FILTER_VALIDATE_EMAIL)
                    ? 'email'
                    : 'username',
                'remembered' => $event->remember,
            ],
        );
    }
}
