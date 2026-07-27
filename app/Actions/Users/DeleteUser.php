<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteUser
{
    public function __construct(private ProtectSuperAdmin $protectSuperAdmin) {}

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

        if ($photoPath !== null) {
            Storage::disk('public')->delete($photoPath);
        }
    }
}
