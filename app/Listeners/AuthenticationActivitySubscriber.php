<?php

namespace App\Listeners;

use App\Activity\ActivityLogger;
use App\Activity\ActivitySanitizer;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;

class AuthenticationActivitySubscriber
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly ActivitySanitizer $sanitizer,
    ) {}

    public function handleFailedLogin(Failed $event): void
    {
        $identifier = $event->credentials['login'] ?? request()->input('login');
        $user = $this->findAttemptedUser($identifier);
        $isUnavailable = $user !== null
            && ($user->account_status !== 'active' || $user->suspended_at !== null);

        $this->logger->log(
            logName: 'authentication',
            event: $isUnavailable ? 'login.blocked' : 'login.failed',
            description: $isUnavailable
                ? 'Blocked a login attempt for an unavailable account.'
                : 'Failed login attempt.',
            subject: $user,
            properties: ['identifier' => $this->sanitizer->maskIdentifier($identifier)],
            status: 'failure',
            failureReason: $isUnavailable ? 'Account unavailable.' : 'Authentication failed.',
            origin: 'guest',
        );
    }

    public function handleLogout(Logout $event): void
    {
        $user = $event->user instanceof Model ? $event->user : null;

        $this->logger->log(
            logName: 'authentication',
            event: 'logout',
            description: $user instanceof User ? "Logged out {$user->name}." : 'Logged out a user.',
            subject: $user,
            causer: $user,
        );
    }

    public function handleLockout(Lockout $event): void
    {
        $this->logger->log(
            logName: 'authentication',
            event: 'login.lockout',
            description: 'Login rate limit reached.',
            properties: [
                'identifier' => $this->sanitizer->maskIdentifier($event->request->input('login')),
            ],
            status: 'failure',
            failureReason: 'Too many login attempts.',
            origin: 'guest',
        );
    }

    public function handlePasswordResetRequested(PasswordResetLinkSent $event): void
    {
        $user = $event->user instanceof Model ? $event->user : null;

        $this->logger->log(
            logName: 'authentication',
            event: 'password.reset_requested',
            description: 'Requested a password reset link.',
            subject: $user,
            origin: 'guest',
        );
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $user = $event->user instanceof Model ? $event->user : null;

        $this->logger->log(
            logName: 'authentication',
            event: 'password.reset',
            description: $user instanceof User ? "Reset the password for {$user->name}." : 'Completed a password reset.',
            subject: $user,
            properties: ['password' => 'Changed'],
            origin: 'guest',
        );
    }

    public function handleEmailVerified(Verified $event): void
    {
        $user = $event->user instanceof Model ? $event->user : null;

        $this->logger->log(
            logName: 'authentication',
            event: 'email.verified',
            description: $user instanceof User ? "Verified the email address for {$user->name}." : 'Verified an email address.',
            subject: $user,
            causer: $user,
        );
    }

    public function handleRegistered(Registered $event): void
    {
        $user = $event->user instanceof Model ? $event->user : null;

        $this->logger->log(
            logName: 'users',
            event: 'user.registered',
            description: $user instanceof User ? "Registered user {$user->name}." : 'Registered a user.',
            subject: $user,
            causer: $user,
            newValues: $user instanceof User ? [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
            ] : [],
        );
    }

    public function handlePasswordUpdated(PasswordUpdatedViaController $event): void
    {
        $this->logUserSecurityEvent($event->user, 'password.changed', 'Changed their account password.');
    }

    public function handleTwoFactorEnabled(TwoFactorAuthenticationEnabled $event): void
    {
        $this->logUserSecurityEvent($event->user, 'two-factor.enabled', 'Enabled two-factor authentication.');
    }

    public function handleTwoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->logUserSecurityEvent($event->user, 'two-factor.confirmed', 'Confirmed two-factor authentication.');
    }

    public function handleTwoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->logUserSecurityEvent($event->user, 'two-factor.disabled', 'Disabled two-factor authentication.');
    }

    public function handleRecoveryCodesGenerated(RecoveryCodesGenerated $event): void
    {
        $this->logUserSecurityEvent($event->user, 'two-factor.recovery_codes_regenerated', 'Regenerated two-factor recovery codes.');
    }

    public function handlePasskeyRegistered(PasskeyRegistered $event): void
    {
        $this->logger->log(
            logName: 'authentication',
            event: 'passkey.registered',
            description: 'Registered a passkey.',
            subject: $event->passkey,
            causer: $event->user,
            properties: ['name' => $event->passkey->name],
        );
    }

    public function handlePasskeyDeleted(PasskeyDeleted $event): void
    {
        $this->logger->log(
            logName: 'authentication',
            event: 'passkey.removed',
            description: 'Removed a passkey.',
            subject: $event->passkey,
            causer: $event->user,
            properties: ['name' => $event->passkey->name],
        );
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Failed::class => 'handleFailedLogin',
            Logout::class => 'handleLogout',
            Lockout::class => 'handleLockout',
            PasswordResetLinkSent::class => 'handlePasswordResetRequested',
            PasswordReset::class => 'handlePasswordReset',
            Verified::class => 'handleEmailVerified',
            Registered::class => 'handleRegistered',
            PasswordUpdatedViaController::class => 'handlePasswordUpdated',
            TwoFactorAuthenticationEnabled::class => 'handleTwoFactorEnabled',
            TwoFactorAuthenticationConfirmed::class => 'handleTwoFactorConfirmed',
            TwoFactorAuthenticationDisabled::class => 'handleTwoFactorDisabled',
            RecoveryCodesGenerated::class => 'handleRecoveryCodesGenerated',
            PasskeyRegistered::class => 'handlePasskeyRegistered',
            PasskeyDeleted::class => 'handlePasskeyDeleted',
        ];
    }

    private function findAttemptedUser(mixed $identifier): ?User
    {
        $identifier = Str::lower(Str::squish((string) $identifier));

        if ($identifier === '') {
            return null;
        }

        return User::query()
            ->where(filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username', $identifier)
            ->first();
    }

    private function logUserSecurityEvent(mixed $user, string $event, string $description): void
    {
        $model = $user instanceof Model ? $user : null;

        $this->logger->log(
            logName: 'authentication',
            event: $event,
            description: $description,
            subject: $model,
            causer: $model,
        );
    }
}
