<?php

namespace App\Policies;

use App\Models\Ministry;
use App\Models\User;
use App\PermissionName;

class MinistryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::MinistriesView);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Ministry $ministry): bool
    {
        return $user->can(PermissionName::MinistriesView);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionName::MinistriesCreate);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Ministry $ministry): bool
    {
        return $user->can(PermissionName::MinistriesUpdate);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Ministry $ministry): bool
    {
        return $user->can(PermissionName::MinistriesDelete);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Ministry $ministry): bool
    {
        return $user->can(PermissionName::MinistriesRestore);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Ministry $ministry): bool
    {
        return $user->can(PermissionName::MinistriesForceDelete);
    }

    public function publish(User $user, Ministry $ministry): bool
    {
        return $user->can(PermissionName::MinistriesPublish);
    }

    public function feature(User $user, Ministry $ministry): bool
    {
        return $user->can(PermissionName::MinistriesUpdate);
    }
}
