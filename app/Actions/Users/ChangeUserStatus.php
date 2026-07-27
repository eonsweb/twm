<?php

namespace App\Actions\Users;

use App\AccountStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChangeUserStatus
{
    public function __construct(private ProtectSuperAdmin $protectSuperAdmin) {}

    public function activate(User $user): User
    {
        $user->update([
            'account_status' => AccountStatus::Active->value,
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        return $user->refresh();
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
    }
}
