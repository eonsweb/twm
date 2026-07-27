<?php

namespace App\Policies;

use App\Models\User;
use App\PermissionName;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::RolesView);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can(PermissionName::RolesView);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::RolesCreate);
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can(PermissionName::RolesUpdate);
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can(PermissionName::RolesDelete);
    }

    public function assignPermissions(User $user, Role $role): bool
    {
        return $user->can(PermissionName::RolesAssignPermissions);
    }
}
