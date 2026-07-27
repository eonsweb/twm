<?php

namespace App\Actions\Roles;

use App\Models\User;
use App\RoleName;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UpdateRole
{
    private const GUARD = 'web';

    /**
     * @param  list<string>  $permissionNames
     */
    public function handle(User $actor, Role $role, string $name, array $permissionNames): Role
    {
        $permissions = $this->validatedPermissions($actor, $permissionNames);

        $updatedRole = DB::transaction(function () use ($role, $name, $permissions): Role {
            $lockedRole = Role::query()
                ->lockForUpdate()
                ->whereKey($role->getKey())
                ->firstOrFail();
            $systemRole = RoleName::tryFrom($lockedRole->name);

            if ($systemRole === RoleName::SuperAdmin) {
                throw ValidationException::withMessages([
                    'form.name' => __('The Super Admin role cannot be modified.'),
                ]);
            }

            if ($systemRole !== null && $name !== $systemRole->value) {
                throw ValidationException::withMessages([
                    'form.name' => __('Protected system roles cannot be renamed.'),
                ]);
            }

            if ($systemRole === null && in_array($name, RoleName::values(), true)) {
                throw ValidationException::withMessages([
                    'form.name' => __('That name is reserved for a protected system role.'),
                ]);
            }

            $lockedRole->update(['name' => $name]);
            $lockedRole->syncPermissions($permissions);

            return $lockedRole->load('permissions');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $updatedRole;
    }

    /**
     * @param  list<string>  $permissionNames
     * @return Collection<int, Permission>
     */
    private function validatedPermissions(User $actor, array $permissionNames): Collection
    {
        $permissions = Permission::query()
            ->where('guard_name', self::GUARD)
            ->whereIn('name', $permissionNames)
            ->get();

        if ($permissions->count() !== count(array_unique($permissionNames))) {
            throw ValidationException::withMessages([
                'form.permissionNames' => __('One or more selected permissions are invalid.'),
            ]);
        }

        if (! $actor->hasRole(RoleName::SuperAdmin)) {
            $grantablePermissionNames = $actor->getAllPermissions()->pluck('name');
            $containsEscalation = $permissions->contains(
                fn (Permission $permission): bool => ! $grantablePermissionNames->contains($permission->name),
            );

            if ($containsEscalation) {
                throw ValidationException::withMessages([
                    'form.permissionNames' => __('You may only grant permissions that you already have.'),
                ]);
            }
        }

        return $permissions;
    }
}
