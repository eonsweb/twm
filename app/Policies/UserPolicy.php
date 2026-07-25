<?php

namespace App\Policies;

use App\Models\User;
use App\PermissionName;
use App\RoleName;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::UsersView);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $target): bool
    {
        return $user->can(PermissionName::UsersView);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionName::UsersCreate);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $target): bool
    {
        return $user->can(PermissionName::UsersUpdate)
            && ($user->hasRole(RoleName::SuperAdmin) || ! $target->hasRole(RoleName::SuperAdmin));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $target): bool
    {
        if (! $user->can(PermissionName::UsersDelete) || $user->is($target)) {
            return false;
        }

        if (! $target->hasRole(RoleName::SuperAdmin)) {
            return true;
        }

        return $user->hasRole(RoleName::SuperAdmin)
            && ! $this->isFinalActiveSuperAdmin($target);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $target): bool
    {
        return $user->can(PermissionName::UsersRestore);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $target): bool
    {
        return $this->delete($user, $target);
    }

    /**
     * Determine whether the user can suspend the target user.
     */
    public function suspend(User $user, User $target): bool
    {
        if (! $user->can(PermissionName::UsersSuspend) || $user->is($target)) {
            return false;
        }

        if (! $target->hasRole(RoleName::SuperAdmin)) {
            return true;
        }

        return $user->hasRole(RoleName::SuperAdmin)
            && ! $this->isFinalActiveSuperAdmin($target);
    }

    /**
     * Determine whether the user can assign the requested roles.
     *
     * @param  list<string>  $roles
     */
    public function assignRoles(User $user, User $target, array $roles): bool
    {
        if (! $user->can(PermissionName::UsersAssignRoles)) {
            return false;
        }

        $assignsSuperAdmin = in_array(RoleName::SuperAdmin->value, $roles, true);
        $removesSuperAdmin = $target->hasRole(RoleName::SuperAdmin) && ! $assignsSuperAdmin;

        if (($assignsSuperAdmin || $target->hasRole(RoleName::SuperAdmin))
            && ! $user->hasRole(RoleName::SuperAdmin)
        ) {
            return false;
        }

        return ! $removesSuperAdmin || ! $this->isFinalActiveSuperAdmin($target);
    }

    /**
     * Determine whether the target is the final active Super Admin.
     */
    private function isFinalActiveSuperAdmin(User $target): bool
    {
        if ($target->account_status !== 'active'
            || $target->suspended_at !== null
            || ! $target->hasRole(RoleName::SuperAdmin)
        ) {
            return false;
        }

        return User::query()
            ->role(RoleName::SuperAdmin)
            ->where('account_status', 'active')
            ->whereNull('suspended_at')
            ->count() <= 1;
    }
}
