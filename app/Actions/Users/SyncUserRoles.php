<?php

namespace App\Actions\Users;

use App\Activity\ActivityLogger;
use App\Models\User;
use App\PermissionName;
use App\RoleName;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SyncUserRoles
{
    public function __construct(
        private readonly ProtectSuperAdmin $protectSuperAdmin,
        private readonly ActivityLogger $activityLogger,
    ) {}

    /**
     * @param  list<int>  $roleIds
     */
    public function handle(User $actor, User $target, array $roleIds): void
    {
        $oldRoleNames = $target->roles()->orderBy('name')->pluck('name')->all();

        DB::transaction(function () use ($actor, $target, $roleIds): void {
            $lockedTarget = User::query()
                ->lockForUpdate()
                ->whereKey($target->getKey())
                ->firstOrFail();
            $roles = Role::query()
                ->where('guard_name', 'web')
                ->whereKey($roleIds)
                ->with('permissions:id,name')
                ->get();

            if ($roles->count() !== count(array_unique($roleIds))) {
                throw ValidationException::withMessages([
                    'form.roleIds' => __('One or more selected roles are invalid.'),
                ]);
            }

            $this->ensureActorMayAssign($actor, $lockedTarget, $roles);

            $removesSuperAdmin = $lockedTarget->hasRole(RoleName::SuperAdmin)
                && ! $roles->contains('name', RoleName::SuperAdmin->value);

            if ($removesSuperAdmin) {
                $this->protectSuperAdmin->ensureNotFinalActiveSuperAdmin($lockedTarget);
            }

            $lockedTarget->syncRoles($roles);
        });

        $newRoleNames = $target->refresh()->roles()->orderBy('name')->pluck('name')->all();

        if ($oldRoleNames !== $newRoleNames) {
            $this->activityLogger->log(
                logName: 'users',
                event: 'user.roles_updated',
                description: "Updated role assignments for {$target->name}.",
                subject: $target,
                causer: $actor,
                properties: [
                    'assigned' => array_values(array_diff($newRoleNames, $oldRoleNames)),
                    'removed' => array_values(array_diff($oldRoleNames, $newRoleNames)),
                ],
                oldValues: ['roles' => $oldRoleNames],
                newValues: ['roles' => $newRoleNames],
            );
        }
    }

    /**
     * @param  Collection<int, Role>  $roles
     */
    private function ensureActorMayAssign(User $actor, User $target, Collection $roles): void
    {
        if (! $actor->hasRole(RoleName::SuperAdmin)
            && ! $actor->hasPermissionTo(PermissionName::UsersAssignRoles)
        ) {
            throw ValidationException::withMessages([
                'form.roleIds' => __('You are not authorized to assign roles.'),
            ]);
        }

        if ($actor->hasRole(RoleName::SuperAdmin)) {
            return;
        }

        if ($target->hasRole(RoleName::SuperAdmin)
            || $roles->contains('name', RoleName::SuperAdmin->value)
        ) {
            throw ValidationException::withMessages([
                'form.roleIds' => __('Only a Super Admin may assign or modify the Super Admin role.'),
            ]);
        }

        $actorPermissionNames = $actor->getAllPermissions()->pluck('name');
        $containsEscalatingRole = $roles->contains(
            fn (Role $role): bool => $role->permissions->contains(
                fn (Permission $permission): bool => ! $actorPermissionNames->contains($permission->name),
            ),
        );

        if ($containsEscalatingRole) {
            throw ValidationException::withMessages([
                'form.roleIds' => __('You may only assign roles whose permissions you already have.'),
            ]);
        }
    }
}
