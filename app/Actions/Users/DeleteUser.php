<?php

namespace App\Actions\Users;

use App\Activity\ActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteUser
{
    public function __construct(
        private readonly ProtectSuperAdmin $protectSuperAdmin,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function handle(User $actor, User $user): void
    {
        $photoPath = $user->photo;

        DB::transaction(function () use ($actor, $user): void {
            $lockedUser = User::query()
                ->lockForUpdate()
                ->whereKey($user->getKey())
                ->firstOrFail();
            $this->protectSuperAdmin->ensureMayDisable($actor, $lockedUser);
            $lockedUser->delete();
        });

        $this->activityLogger->log(
            logName: 'users',
            event: 'user.deleted',
            description: "Deleted user {$user->name}.",
            subject: $user,
            causer: $actor,
            oldValues: [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'account_status' => $user->account_status,
            ],
        );

        if ($photoPath !== null) {
            Storage::disk('public')->delete($photoPath);
        }
    }
}
