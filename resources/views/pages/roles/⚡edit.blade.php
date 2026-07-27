<?php

use App\Actions\Roles\UpdateRole;
use App\Livewire\Forms\RoleForm;
use App\Models\User;
use App\PermissionName;
use App\RoleName;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

new #[Title('Edit role')] class extends Component
{
    public Role $role;

    public RoleForm $form;

    public function mount(Role $role): void
    {
        abort_unless($role->guard_name === 'web', 404);
        Gate::authorize('update', $role);

        $this->role = $role;
        $this->form->setRole($role);
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

    #[Computed]
    public function isImmutable(): bool
    {
        return $this->role->name === RoleName::SuperAdmin->value;
    }

    public function togglePermissionGroup(string $module): void
    {
        Gate::authorize('assignPermissions', $this->role);

        $groupNames = $this->permissionGroups->get($module, collect())->pluck('name')->all();
        $allSelected = count(array_intersect($this->form->permissionNames, $groupNames)) === count($groupNames);

        $this->form->permissionNames = $allSelected
            ? array_values(array_diff($this->form->permissionNames, $groupNames))
            : array_values(array_unique([...$this->form->permissionNames, ...$groupNames]));
    }

    public function selectAllPermissions(): void
    {
        Gate::authorize('assignPermissions', $this->role);

        $this->form->permissionNames = $this->permissionGroups->flatten()->pluck('name')->values()->all();
    }

    public function clearAllPermissions(): void
    {
        Gate::authorize('assignPermissions', $this->role);

        $this->form->permissionNames = [];
    }

    public function save(UpdateRole $updateRole): void
    {
        Gate::authorize('update', $this->role);

        $this->form->normalize();
        $this->form->validate();
        Gate::authorize('assignPermissions', $this->role);

        /** @var User $actor */
        $actor = auth()->user();
        $this->role = $updateRole->handle(
            $actor,
            $this->role,
            $this->form->name,
            $this->form->normalizedPermissionNames(),
        );
        $this->form->setRole($this->role);

        Flux::toast(variant: 'success', text: __('Role updated.'));
    }

    public function roleLabel(): string
    {
        return RoleName::fromStoredName($this->role->name)?->label() ?? Str::headline($this->role->name);
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('admin.roles.index')" wire:navigate>{{ __('Roles & permissions') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $this->roleLabel() }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-admin.page-header
        :title="__('Edit :role', ['role' => $this->roleLabel()])"
        :description="__('Update the role name and its module-level access.')"
        :eyebrow="__('Administration')"
    />

    @if ($this->isImmutable)
        <flux:callout variant="warning" icon="lock-closed">
            <flux:callout.heading>{{ __('Protected Super Admin role') }}</flux:callout.heading>
            <flux:callout.text>{{ __('This role always receives every permission and cannot be modified.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <form wire:submit="save" class="space-y-6">
        <x-admin.role-form
            :form="$form"
            :permission-groups="$this->permissionGroups"
            :can-assign-permissions="! $this->isImmutable && auth()->user()->can('assignPermissions', $role)"
            :name-disabled="$this->isImmutable || \App\RoleName::tryFrom($role->name) !== null"
        />

        <div class="sticky bottom-4 z-20 flex flex-wrap justify-end gap-3 rounded-xl border border-slate-200/80 bg-white/95 p-4 shadow-lg backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95">
            <flux:button :href="route('admin.roles.index')" variant="ghost" wire:navigate>{{ __('Back') }}</flux:button>
            @if (! $this->isImmutable)
                <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">
                    {{ __('Save changes') }}
                </flux:button>
            @endif
        </div>
    </form>
</div>
