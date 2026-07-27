<?php

namespace App\Actions\Roles;

use App\RoleName;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DeleteRole
{
    public function handle(Role $role): void
    {
        DB::transaction(function () use ($role): void {
            $lockedRole = Role::query()
                ->lockForUpdate()
                ->whereKey($role->getKey())
                ->firstOrFail();

            if (RoleName::tryFrom($lockedRole->name)?->isProtected() === true) {
                throw ValidationException::withMessages([
                    'deleteRole' => __('Protected system roles cannot be deleted.'),
                ]);
            }

            $assignedUserCount = $lockedRole->users()->count();

            if ($assignedUserCount > 0) {
                throw ValidationException::withMessages([
                    'deleteRole' => trans_choice(
                        'This role is assigned to :count user and cannot be deleted.|This role is assigned to :count users and cannot be deleted.',
                        $assignedUserCount,
                        ['count' => $assignedUserCount],
                    ),
                ]);
            }

            $lockedRole->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
