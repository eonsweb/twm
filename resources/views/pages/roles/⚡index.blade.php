<?php

use App\Actions\Roles\DeleteRole;
use App\PermissionName;
use App\RoleName;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new #[Title('Roles & Permissions')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public int $perPage = 10;

    public ?int $viewingRoleId = null;

    public ?int $deletingRoleId = null;

    public bool $showRoleModal = false;

    public bool $showDeleteModal = false;

    public function mount(): void
    {
        Gate::authorize('viewAny', Role::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function roles(): LengthAwarePaginator
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->when($this->search !== '', function (Builder $query): void {
                $query->where('name', 'like', '%'.Str::kebab(trim($this->search)).'%');
            })
            ->withCount(['permissions', 'users'])
            ->latest()
            ->paginate($this->perPage);
    }

    #[Computed]
    public function viewingRole(): ?Role
    {
        if ($this->viewingRoleId === null) {
            return null;
        }

        return Role::query()
            ->where('guard_name', 'web')
            ->with([
                'permissions:id,name',
                'users' => fn ($query) => $query
                    ->select('users.id', 'users.name', 'users.email')
                    ->orderBy('users.name')
                    ->limit(25),
            ])
            ->withCount('users')
            ->find($this->viewingRoleId);
    }

    public function viewRole(int $roleId): void
    {
        $role = Role::query()->where('guard_name', 'web')->findOrFail($roleId);
        Gate::authorize('view', $role);

        $this->viewingRoleId = (int) $role->getKey();
        unset($this->viewingRole);
        $this->showRoleModal = true;
    }

    public function confirmDelete(int $roleId): void
    {
        $role = Role::query()->where('guard_name', 'web')->findOrFail($roleId);
        Gate::authorize('delete', $role);

        $this->deletingRoleId = (int) $role->getKey();
        $this->resetErrorBag('deleteRole');
        $this->showDeleteModal = true;
    }

    public function delete(DeleteRole $deleteRole): void
    {
        $role = Role::query()->where('guard_name', 'web')->findOrFail($this->deletingRoleId);
        Gate::authorize('delete', $role);

        $deleteRole->handle($role);

        $this->reset(['deletingRoleId', 'showDeleteModal']);
        unset($this->roles);

        Flux::toast(variant: 'success', text: __('Role deleted.'));
    }

    public function roleLabel(string $name): string
    {
        return RoleName::fromStoredName($name)?->label() ?? Str::headline($name);
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Roles & permissions') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-admin.page-header
        :title="__('Roles & Permissions')"
        :description="__('Manage administrative roles, review assigned users, and control access by application module.')"
        :eyebrow="__('Administration')"
    >
        <x-slot:actions>
            @can(PermissionName::RolesCreate->value)
                <flux:button :href="route('admin.roles.create')" variant="primary" icon="plus" wire:navigate>
                    {{ __('Create role') }}
                </flux:button>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <section class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
        <div class="border-b border-slate-100 p-4 dark:border-zinc-800">
            <div class="max-w-xl">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    :placeholder="__('Search roles…')"
                    :label="__('Search roles')"
                />
            </div>
        </div>

        <div class="px-4 py-4 sm:px-6 sm:py-6 md:px-8 md:py-8">
            <div class="relative">
                <div wire:loading.flex class="absolute inset-0 z-10 items-center justify-center bg-white/75 backdrop-blur-sm dark:bg-zinc-900/75">
                    <div class="flex items-center gap-2 text-sm font-medium text-church-maroon-800 dark:text-church-gold-400">
                        <flux:icon.arrow-path class="size-4 animate-spin" />
                        {{ __('Loading roles…') }}
                    </div>
                </div>

                @if ($this->roles->isEmpty())
                    <x-admin.empty-state
                        icon="key"
                        :title="__('No roles found')"
                        :description="$search !== '' ? __('Try a different role name.') : __('Create the first custom role for your administration team.')"
                    />
                @else
                    <div class="overflow-x-auto">
                        <flux:table :paginate="$this->roles">
                            <flux:table.columns>
                                <flux:table.column>{{ __('Role name') }}</flux:table.column>
                                <flux:table.column>{{ __('Permissions') }}</flux:table.column>
                                <flux:table.column>{{ __('Assigned users') }}</flux:table.column>
                                <flux:table.column class="hidden md:table-cell">{{ __('Created') }}</flux:table.column>
                                <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @foreach ($this->roles as $role)
                                    @php($systemRole = RoleName::tryFrom($role->name))

                                    <flux:table.row :key="$role->id" wire:key="role-row-{{ $role->id }}">
                                        <flux:table.cell>
                                            <div class="min-w-48">
                                                <div class="flex items-center gap-2">
                                                    <p class="font-semibold text-slate-900 dark:text-white">{{ $this->roleLabel($role->name) }}</p>
                                                    @if ($systemRole)
                                                        <flux:badge size="sm" color="amber">{{ __('System') }}</flux:badge>
                                                    @endif
                                                </div>
                                                <p class="mt-1 font-mono text-xs text-slate-500 dark:text-zinc-400">{{ $role->name }}</p>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <flux:badge size="sm" color="zinc">{{ $role->permissions_count }}</flux:badge>
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <flux:badge size="sm" :color="$role->users_count > 0 ? 'blue' : 'zinc'">{{ $role->users_count }}</flux:badge>
                                        </flux:table.cell>
                                        <flux:table.cell class="hidden md:table-cell">
                                            {{ $role->created_at?->format('M j, Y') }}
                                        </flux:table.cell>
                                        <flux:table.cell align="end">
                                            <div class="flex justify-end gap-1">
                                                <flux:button
                                                    wire:click="viewRole({{ $role->id }})"
                                                    variant="ghost"
                                                    size="sm"
                                                    icon="eye"
                                                    :aria-label="__('View :role permissions', ['role' => $this->roleLabel($role->name)])"
                                                />
                                                @can('update', $role)
                                                    @if ($systemRole !== RoleName::SuperAdmin)
                                                        <flux:button
                                                            :href="route('admin.roles.edit', $role)"
                                                            variant="ghost"
                                                            size="sm"
                                                            icon="pencil-square"
                                                            :aria-label="__('Edit :role', ['role' => $this->roleLabel($role->name)])"
                                                            wire:navigate
                                                        />
                                                    @endif
                                                @endcan
                                                @can('delete', $role)
                                                    @if (! $systemRole)
                                                        <flux:button
                                                            wire:click="confirmDelete({{ $role->id }})"
                                                            variant="ghost"
                                                            size="sm"
                                                            icon="trash"
                                                            class="text-red-600 hover:text-red-700 dark:text-red-400"
                                                            :aria-label="__('Delete :role', ['role' => $this->roleLabel($role->name)])"
                                                        />
                                                    @endif
                                                @endcan
                                            </div>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                @endif
            </div>
        </div>
    </section>

    <flux:modal wire:model="showRoleModal" class="max-w-3xl">
        @if ($this->viewingRole)
            <div class="space-y-6">
                <div>
                    <flux:heading size="xl">{{ $this->roleLabel($this->viewingRole->name) }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ trans_choice(':count assigned user|:count assigned users', $this->viewingRole->users_count, ['count' => $this->viewingRole->users_count]) }}
                        ·
                        {{ trans_choice(':count permission|:count permissions', $this->viewingRole->permissions->count(), ['count' => $this->viewingRole->permissions->count()]) }}
                    </flux:text>
                </div>

                <div>
                    <flux:heading size="lg">{{ __('Permissions') }}</flux:heading>
                    <div class="mt-3 flex max-h-64 flex-wrap gap-2 overflow-y-auto">
                        @forelse ($this->viewingRole->permissions->sortBy('name') as $permission)
                            <flux:badge size="sm" color="zinc" wire:key="view-role-permission-{{ $permission->id }}">
                                {{ $permission->name }}
                            </flux:badge>
                        @empty
                            <flux:text>{{ __('No permissions assigned.') }}</flux:text>
                        @endforelse
                    </div>
                </div>

                <div>
                    <flux:heading size="lg">{{ __('Assigned users') }}</flux:heading>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        @forelse ($this->viewingRole->users as $user)
                            <a
                                href="{{ route('users.show', $user) }}"
                                wire:navigate
                                class="rounded-lg border border-slate-200 p-3 transition hover:border-church-maroon-300 hover:bg-slate-50 dark:border-zinc-700 dark:hover:border-zinc-600 dark:hover:bg-zinc-800"
                                wire:key="view-role-user-{{ $user->id }}"
                            >
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $user->name }}</p>
                                <p class="truncate text-xs text-slate-500 dark:text-zinc-400">{{ $user->email }}</p>
                            </a>
                        @empty
                            <flux:text>{{ __('No users are assigned to this role.') }}</flux:text>
                        @endforelse
                    </div>
                </div>

                <div class="flex justify-end">
                    <flux:button type="button" variant="ghost" wire:click="$set('showRoleModal', false)">{{ __('Close') }}</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="max-w-lg">
        <form wire:submit="delete" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete role?') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Deletion is blocked when the role is protected or still assigned to users.') }}</flux:text>
            </div>

            <flux:error name="deleteRole" />

            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="$set('showDeleteModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="danger" icon="trash" wire:loading.attr="disabled">{{ __('Delete role') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
