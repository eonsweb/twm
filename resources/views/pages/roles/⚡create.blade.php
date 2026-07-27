<?php

use App\Actions\Roles\CreateRole;
use App\Livewire\Forms\RoleForm;
use App\Models\User;
use App\PermissionName;
use App\RoleName;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

new #[Title('Create role')] class extends Component
{
    public RoleForm $form;

    public function mount(): void
    {
        Gate::authorize('create', Role::class);
    }

    #[Computed]
    public function permissionGroups()
    {
        /** @var User $actor */
        $actor = auth()->user();

        return Permission::query()
            ->where('guard_name', 'web')
            ->when(
                ! $actor->hasRole(RoleName::SuperAdmin),
                fn ($query) => $query->whereIn('name', $actor->getAllPermissions()->pluck('name')),
            )
            ->orderBy('name')
            ->get(['id', 'name'])
            ->groupBy(fn (Permission $permission): string => str($permission->name)->before('.')->toString());
    }

    public function togglePermissionGroup(string $module): void
    {
        Gate::authorize(PermissionName::RolesAssignPermissions->value);

        $groupNames = $this->permissionGroups->get($module, collect())->pluck('name')->all();
        $allSelected = count(array_intersect($this->form->permissionNames, $groupNames)) === count($groupNames);

        $this->form->permissionNames = $allSelected
            ? array_values(array_diff($this->form->permissionNames, $groupNames))
            : array_values(array_unique([...$this->form->permissionNames, ...$groupNames]));
    }

    public function selectAllPermissions(): void
    {
        Gate::authorize(PermissionName::RolesAssignPermissions->value);

        $this->form->permissionNames = $this->permissionGroups->flatten()->pluck('name')->values()->all();
    }

    public function clearAllPermissions(): void
    {
        Gate::authorize(PermissionName::RolesAssignPermissions->value);

        $this->form->permissionNames = [];
    }

    public function save(CreateRole $createRole): void
    {
        Gate::authorize('create', Role::class);

        $this->form->normalize();
        $this->form->validate();

        if ($this->form->permissionNames !== []) {
            Gate::authorize(PermissionName::RolesAssignPermissions->value);
        }

        /** @var User $actor */
        $actor = auth()->user();
        $role = $createRole->handle($actor, $this->form->name, $this->form->normalizedPermissionNames());

        Flux::toast(variant: 'success', text: __('Role created.'));
        $this->redirectRoute('admin.roles.edit', $role, navigate: true);
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('admin.roles.index')" wire:navigate>{{ __('Roles & permissions') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Create role') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-admin.page-header
        :title="__('Create role')"
        :description="__('Create a focused role and grant only the permissions its administrators need.')"
        :eyebrow="__('Administration')"
    />

    <form wire:submit="save" class="space-y-6">
        <x-admin.role-form
            :form="$form"
            :permission-groups="$this->permissionGroups"
            :can-assign-permissions="auth()->user()->can(PermissionName::RolesAssignPermissions)"
        />

        <div class="sticky bottom-4 z-20 flex flex-wrap justify-end gap-3 rounded-xl border border-slate-200/80 bg-white/95 p-4 shadow-lg backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95">
            <flux:button :href="route('admin.roles.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">
                {{ __('Create role') }}
            </flux:button>
        </div>
    </form>
</div>
