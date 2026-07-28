<?php

namespace App\Actions\Roles;

use App\Activity\ActivityLogger;
use App\Models\User;
use App\RoleName;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CreateRole
{
    private const GUARD = 'web';

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * @param  list<string>  $permissionNames
     */
    public function handle(User $actor, string $name, array $permissionNames): Role
    {
        if (in_array($name, RoleName::values(), true)) {
            throw ValidationException::withMessages([
                'form.name' => __('That name is reserved for a protected system role.'),
            ]);
        }

        $permissions = $this->validatedPermissions($actor, $permissionNames);

        $role = DB::transaction(function () use ($name, $permissions): Role {
            $role = Role::query()->create([
                'name' => $name,
                'guard_name' => self::GUARD,
            ]);
            $role->syncPermissions($permissions);

            return $role->load('permissions');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->activityLogger->log(
            logName: 'roles',
            event: 'role.created',
            description: "Created role {$role->name}.",
            subject: $role,
            causer: $actor,
            newValues: [
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
            ],
        );

        return $role;
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
