<?php

namespace App\Livewire\Forms;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleForm extends Form
{
    public ?int $roleId = null;

    public string $name = '';

    /** @var array<int, mixed> */
    public array $permissionNames = [];

    public function setRole(Role $role): void
    {
        $role->loadMissing('permissions:id,name');

        $this->roleId = (int) $role->getKey();
        $this->name = $role->name;
        $this->permissionNames = $role->permissions
            ->map(fn (Permission $permission): string => $permission->name)
            ->sort()
            ->values()
            ->all();
    }

    public function normalize(): void
    {
        $this->name = Str::kebab(Str::lower(Str::squish($this->name)));
        $this->permissionNames = $this->normalizedPermissionNames();
    }

    /**
     * @return list<string>
     */
    public function normalizedPermissionNames(): array
    {
        return array_values(collect($this->permissionNames)
            ->filter(fn (mixed $permission): bool => is_string($permission))
            ->map(fn (string $permission): string => trim($permission))
            ->filter(fn (string $permission): bool => $permission !== '')
            ->unique()
            ->values()
            ->all());
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',
                Rule::unique(Role::class, 'name')
                    ->where('guard_name', 'web')
                    ->ignore($this->roleId),
            ],
            'permissionNames' => ['array'],
            'permissionNames.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists(Permission::class, 'name')->where('guard_name', 'web'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'name' => __('role name'),
            'permissionNames' => __('permissions'),
            'permissionNames.*' => __('selected permission'),
        ];
    }
}
