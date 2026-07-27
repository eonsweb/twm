<?php

use App\AccountStatus;
use App\Models\User;
use App\PermissionName;
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

new #[Title('Users')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $role = '';

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $verification = 'all';

    #[Url]
    public string $sort = 'created_at';

    #[Url]
    public string $direction = 'desc';

    public int $perPage = 10;

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRole(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedVerification(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, ['name', 'username', 'account_status', 'last_login_at', 'created_at'], true)) {
            return;
        }

        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->direction = $column === 'name' || $column === 'username' ? 'asc' : 'desc';
        }

        $this->resetPage();
    }

    #[Computed]
    public function roles()
    {
        return Role::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->select([
                'id',
                'name',
                'username',
                'email',
                'email_verified_at',
                'photo',
                'account_status',
                'last_login_at',
                'created_at',
            ])
            ->with('roles:id,name')
            ->when($this->search !== '', function (Builder $query): void {
                $search = '%'.Str::lower(trim($this->search)).'%';

                $query->where(function (Builder $query) use ($search): void {
                    $query->whereRaw('LOWER(name) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(username) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$search]);
                });
            })
            ->when(
                $this->role !== '',
                fn (Builder $query) => $query->whereHas(
                    'roles',
                    fn (Builder $query) => $query->whereKey((int) $this->role),
                ),
            )
            ->when(
                $this->status !== 'all',
                fn (Builder $query) => $query->where('account_status', $this->status),
            )
            ->when(
                $this->verification === 'verified',
                fn (Builder $query) => $query->whereNotNull('email_verified_at'),
            )
            ->when(
                $this->verification === 'unverified',
                fn (Builder $query) => $query->whereNull('email_verified_at'),
            )
            ->orderBy($this->sort, $this->direction)
            ->orderByDesc('id')
            ->paginate($this->perPage);
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Users') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-admin.page-header
        :title="__('Users')"
        :description="__('Manage administrator identities, access roles, account state, and security readiness.')"
        :eyebrow="__('Administration')"
    >
        <x-slot:actions>
            @can(PermissionName::UsersCreate->value)
                <flux:button :href="route('users.create')" variant="primary" icon="user-plus" wire:navigate>
                    {{ __('Create user') }}
                </flux:button>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <section class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
        <div class="grid gap-4 border-b border-slate-100 p-4 md:grid-cols-2 xl:grid-cols-[minmax(14rem,1fr)_repeat(3,minmax(10rem,0.35fr))] dark:border-zinc-800">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search name, username, or email…')" :label="__('Search users')" />

            <flux:select wire:model.live="role" :label="__('Role')">
                <flux:select.option value="">{{ __('All roles') }}</flux:select.option>
                @foreach ($this->roles as $filterRole)
                    <flux:select.option :value="$filterRole->id" wire:key="user-filter-role-{{ $filterRole->id }}">
                        {{ $filterRole->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="status" :label="__('Account status')">
                <flux:select.option value="all">{{ __('All statuses') }}</flux:select.option>
                @foreach (AccountStatus::cases() as $accountStatus)
                    <flux:select.option :value="$accountStatus->value">{{ __($accountStatus->label()) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="verification" :label="__('Email verification')">
                <flux:select.option value="all">{{ __('All') }}</flux:select.option>
                <flux:select.option value="verified">{{ __('Verified') }}</flux:select.option>
                <flux:select.option value="unverified">{{ __('Pending') }}</flux:select.option>
            </flux:select>
        </div>

        <div class="px-4 py-4 sm:px-6 sm:py-6 md:px-8 md:py-8">
            <div class="relative">
                <div wire:loading.flex class="absolute inset-0 z-10 items-center justify-center bg-white/75 backdrop-blur-sm dark:bg-zinc-900/75">
                    <div class="flex items-center gap-2 text-sm font-medium text-church-maroon-800 dark:text-church-gold-400">
                        <flux:icon.arrow-path class="size-4 animate-spin" />
                        {{ __('Loading users…') }}
                    </div>
                </div>

                @if ($this->users->isEmpty())
                    <x-admin.empty-state
                        icon="users"
                        :title="__('No users found')"
                        :description="$search || $role || $status !== 'all' || $verification !== 'all'
                            ? __('Try changing the search or filters.')
                            : __('Invite the first administrator account.')"
                    />
                @else
                    <flux:table :paginate="$this->users">
                        <flux:table.columns>
                            <flux:table.column sortable :sorted="$sort === 'name'" :direction="$direction" wire:click="sortBy('name')">{{ __('Administrator') }}</flux:table.column>
                            <flux:table.column>{{ __('Roles') }}</flux:table.column>
                            <flux:table.column sortable :sorted="$sort === 'account_status'" :direction="$direction" wire:click="sortBy('account_status')">{{ __('Status') }}</flux:table.column>
                            <flux:table.column>{{ __('Verification') }}</flux:table.column>
                            <flux:table.column sortable :sorted="$sort === 'last_login_at'" :direction="$direction" wire:click="sortBy('last_login_at')" class="hidden lg:table-cell">{{ __('Last login') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->users as $administrator)
                                <flux:table.row :key="$administrator->id" wire:key="administrator-row-{{ $administrator->id }}">
                                    <flux:table.cell>
                                        <div class="flex min-w-52 items-center gap-3">
                                            <flux:avatar :src="$administrator->photoUrl()" :name="$administrator->name" size="sm" color="auto" :color:seed="$administrator->id" />
                                            <div class="min-w-0">
                                                <p class="truncate font-semibold text-slate-900 dark:text-white">
                                                    {{ $administrator->name }}
                                                    @if (auth()->user()->is($administrator))
                                                        <flux:badge size="sm" color="blue">{{ __('You') }}</flux:badge>
                                                    @endif
                                                </p>
                                                <p class="truncate text-xs text-slate-500 dark:text-zinc-400">{{ '@'.$administrator->username }} · {{ $administrator->email }}</p>
                                            </div>
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex max-w-64 flex-wrap gap-1.5">
                                            @forelse ($administrator->roles as $administratorRole)
                                                <flux:badge size="sm" color="zinc" wire:key="administrator-{{ $administrator->id }}-role-{{ $administratorRole->id }}">
                                                    {{ \App\RoleName::fromStoredName($administratorRole->name)?->label() ?? \Illuminate\Support\Str::headline($administratorRole->name) }}
                                                </flux:badge>
                                            @empty
                                                <span class="text-xs text-amber-700 dark:text-amber-400">{{ __('No role') }}</span>
                                            @endforelse
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge :color="$administrator->accountStatus()->badgeColor()" size="sm">
                                            {{ __($administrator->accountStatus()->label()) }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge :color="$administrator->hasVerifiedEmail() ? 'green' : 'amber'" size="sm">
                                            {{ $administrator->hasVerifiedEmail() ? __('Verified') : __('Pending') }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell class="hidden lg:table-cell">
                                        {{ $administrator->last_login_at?->diffForHumans() ?? __('Never') }}
                                    </flux:table.cell>
                                    <flux:table.cell align="end">
                                        <div class="flex justify-end gap-1">
                                            <flux:button :href="route('users.show', $administrator)" variant="ghost" size="sm" icon="eye" :aria-label="__('View :name', ['name' => $administrator->name])" wire:navigate />
                                            @can('update', $administrator)
                                                <flux:button :href="route('users.edit', $administrator)" variant="ghost" size="sm" icon="pencil-square" :aria-label="__('Edit :name', ['name' => $administrator->name])" wire:navigate />
                                            @endcan
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </div>
        </div>
    </section>
</div>
