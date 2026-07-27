<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\User;
use App\PermissionName;

class PersonPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::LeadershipView);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Person $person): bool
    {
        return $user->can(PermissionName::LeadershipView);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionName::LeadershipCreate);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Person $person): bool
    {
        return $user->can(PermissionName::LeadershipUpdate);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Person $person): bool
    {
        return $user->can(PermissionName::LeadershipDelete);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Person $person): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Person $person): bool
    {
        return false;
    }

    public function publish(User $user, Person $person): bool
    {
        return $user->can(PermissionName::LeadershipPublish);
    }

    public function reorder(User $user): bool
    {
        return $user->can(PermissionName::LeadershipReorder);
    }
}
