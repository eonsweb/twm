<?php

namespace App\Actions\Users;

use App\AccountStatus;
use App\Activity\ActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChangeUserStatus
{
    public function __construct(
        private readonly ProtectSuperAdmin $protectSuperAdmin,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function activate(User $actor, User $user): User
    {
        $oldStatus = $user->account_status;
        $user->update([
            'account_status' => AccountStatus::Active->value,
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        $user = $user->refresh();
        $this->activityLogger->log(
            logName: 'users',
            event: 'user.reactivated',
            description: "Reactivated user {$user->name}.",
            subject: $user,
            causer: $actor,
            oldValues: ['account_status' => $oldStatus],
            newValues: ['account_status' => $user->account_status],
        );

        return $user;
    }

    public function deactivate(User $actor, User $user): User
    {
        $this->disableSessions($actor, $user, AccountStatus::Inactive);

        return $user->refresh();
    }

    public function suspend(User $actor, User $user, string $reason): User
    {
        $this->disableSessions($actor, $user, AccountStatus::Suspended, Str::squish($reason));

        return $user->refresh();
    }

    private function disableSessions(User $actor, User $user, AccountStatus $status, ?string $reason = null): void
    {
        $oldStatus = $user->account_status;

        DB::transaction(function () use ($actor, $user, $status, $reason): void {
            $lockedUser = User::query()
                ->lockForUpdate()
                ->whereKey($user->getKey())
                ->firstOrFail();
            $this->protectSuperAdmin->ensureMayDisable($actor, $lockedUser);

            $lockedUser->forceFill([
                'account_status' => $status->value,
                'suspended_at' => $status === AccountStatus::Suspended ? now() : null,
                'suspension_reason' => $status === AccountStatus::Suspended ? $reason : null,
                'remember_token' => Str::random(60),
            ])->save();

            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $lockedUser->id)
                ->delete();
        });

        $freshUser = $user->refresh();
        $event = $status === AccountStatus::Suspended ? 'user.suspended' : 'user.deactivated';
        $verb = $status === AccountStatus::Suspended ? 'Suspended' : 'Deactivated';

        $this->activityLogger->log(
            logName: 'users',
            event: $event,
            description: "{$verb} user {$freshUser->name}.",
            subject: $freshUser,
            causer: $actor,
            properties: $reason === null ? [] : ['reason' => $reason],
            oldValues: ['account_status' => $oldStatus],
            newValues: ['account_status' => $freshUser->account_status],
        );
    }
}
