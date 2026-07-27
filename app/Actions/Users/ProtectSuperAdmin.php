<?php

namespace App\Actions\Users;

use App\AccountStatus;
use App\Models\User;
use App\RoleName;
use Illuminate\Validation\ValidationException;

class ProtectSuperAdmin
{
    public function ensureMayDisable(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw ValidationException::withMessages([
                'user' => __('You cannot disable or delete your own account.'),
            ]);
        }

        if (! $target->hasRole(RoleName::SuperAdmin)) {
            return;
        }

        if (! $actor->hasRole(RoleName::SuperAdmin)) {
            throw ValidationException::withMessages([
                'user' => __('Only a Super Admin may modify another Super Admin account.'),
            ]);
        }

        $this->ensureNotFinalActiveSuperAdmin($target);
    }

    public function ensureNotFinalActiveSuperAdmin(User $target): void
    {
        if ($target->account_status !== AccountStatus::Active->value
            || $target->suspended_at !== null
            || ! $target->hasRole(RoleName::SuperAdmin)
        ) {
            return;
        }

        $activeSuperAdminIds = User::query()
            ->role(RoleName::SuperAdmin)
            ->where('account_status', AccountStatus::Active->value)
            ->whereNull('suspended_at')
            ->select('users.id')
            ->lockForUpdate()
            ->pluck('users.id')
            ->all();

        if (count($activeSuperAdminIds) <= 1) {
            throw ValidationException::withMessages([
                'user' => __('The final active Super Admin assignment cannot be removed.'),
            ]);
        }
    }
}
