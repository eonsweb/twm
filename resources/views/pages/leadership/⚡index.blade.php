<?php

use App\Actions\Leadership\DeleteLeader;
use App\Models\LeadershipPosition;
use App\Models\Person;
use App\PermissionName;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Leadership')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $position = '';

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $sort = 'order';

    #[Url]
    public string $direction = 'asc';

    public int $perPage = 10;

    public function mount(): void
    {
        Gate::authorize('viewAny', Person::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPosition(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, ['name', 'status', 'visibility', 'order'], true)) {
            return;
        }

        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->direction = 'asc';
        }

        $this->resetPage();
    }

    #[Computed]
    public function positions()
    {
        return LeadershipPosition::query()
            ->orderBy('rank')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function leaders(): LengthAwarePaginator
    {
        $query = Person::query()
            ->select([
                'id',
                'title',
                'first_name',
                'middle_name',
                'last_name',
                'slug',
                'photo_path',
                'is_active',
                'is_public',
            ])
            ->leaders()
            ->withMin('currentLeadershipAssignments as display_order', 'sort_order')
            ->with([
                'currentLeadershipAssignments' => fn ($query) => $query
                    ->select([
                        'id',
                        'person_id',
                        'leadership_position_id',
                        'display_title',
                        'is_primary',
                        'sort_order',
                    ])
                    ->with('position:id,name,rank')
                    ->orderByDesc('is_primary')
                    ->orderBy('sort_order'),
            ])
            ->when($this->search !== '', function (Builder $query): void {
                $search = '%'.Str::lower(trim($this->search)).'%';

                $query->where(function (Builder $query) use ($search): void {
                    $query->whereRaw('LOWER(first_name) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(middle_name) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$search]);
                });
            })
            ->when($this->position !== '', function (Builder $query): void {
                $query->whereHas(
                    'currentLeadershipAssignments',
                    fn (Builder $query) => $query->where('leadership_position_id', (int) $this->position),
                );
            })
            ->when($this->status === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($this->status === 'inactive', fn (Builder $query) => $query->where('is_active', false));

        match ($this->sort) {
            'name' => $query->orderBy('last_name', $this->direction)->orderBy('first_name', $this->direction),
            'status' => $query->orderBy('is_active', $this->direction),
            'visibility' => $query->orderBy('is_public', $this->direction),
            default => $query->orderBy('display_order', $this->direction)->orderBy('last_name'),
        };

        return $query->paginate($this->perPage);
    }

    public function toggleActive(Person $person): void
    {
        Gate::authorize('update', $person);

        $person->update(['is_active' => ! $person->is_active]);
        unset($this->leaders);

        Flux::toast(variant: 'success', text: __('Leader status updated.'));
    }

    public function toggleVisibility(Person $person): void
    {
        Gate::authorize('publish', $person);

        $person->update(['is_public' => ! $person->is_public]);
        unset($this->leaders);

        Flux::toast(variant: 'success', text: __('Public visibility updated.'));
    }

    public function delete(Person $person, DeleteLeader $deleteLeader): void
    {
        Gate::authorize('delete', $person);

        $deleteLeader->handle($person);
        unset($this->leaders);

        Flux::toast(variant: 'success', text: __('Leader deleted.'));
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Leadership') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-admin.page-header
        :title="__('Leadership')"
        :description="__('Manage reusable people profiles, ministry positions, visibility, and public display order.')"
        :eyebrow="__('Content management')"
    >
        <x-slot:actions>
            <flux:button :href="route('leadership.positions')" variant="outline" icon="briefcase" wire:navigate>
                {{ __('Positions') }}
            </flux:button>
            @can(PermissionName::LeadershipReorder->value)
                <flux:button :href="route('leadership.ordering')" variant="outline" icon="bars-3" wire:navigate>
                    {{ __('Order') }}
                </flux:button>
            @endcan
            @can(PermissionName::LeadershipCreate->value)
                <flux:button :href="route('leadership.create')" variant="primary" icon="plus" wire:navigate>
                    {{ __('Add leader') }}
                </flux:button>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <section class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
        <div class="grid gap-4 border-b border-slate-100 p-4 md:grid-cols-[minmax(14rem,1fr)_minmax(12rem,0.45fr)_minmax(10rem,0.35fr)] dark:border-zinc-800">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by name…')" :label="__('Search leaders')" />

            <flux:select wire:model.live="position" :label="__('Position')">
                <flux:select.option value="">{{ __('All positions') }}</flux:select.option>
                @foreach ($this->positions as $leadershipPosition)
                    <flux:select.option :value="$leadershipPosition->id" wire:key="filter-position-{{ $leadershipPosition->id }}">
                        {{ $leadershipPosition->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="status" :label="__('Status')">
                <flux:select.option value="all">{{ __('All statuses') }}</flux:select.option>
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
            </flux:select>
        </div>
        <div class="px-4 sm:px-6 md:px-8 py-4 sm:py-6  md:py-8">
            <div class="relative">
                <div wire:loading.flex class="absolute inset-0 z-10 items-center justify-center bg-white/75 backdrop-blur-sm dark:bg-zinc-900/75">
                    <div class="flex items-center gap-2 text-sm font-medium text-church-maroon-800 dark:text-church-gold-400">
                        <flux:icon.arrow-path class="size-4 animate-spin" />
                        {{ __('Loading leadership…') }}
                    </div>
                </div>

                @if ($this->leaders->isEmpty())
                    <x-admin.empty-state
                        icon="identification"
                        :title="__('No leaders found')"
                        :description="$search || $position || $status !== 'all'
                            ? __('Try changing the search or filters.')
                            : __('Add the first leader to begin building the directory.')"
                    />
                @else
                    <flux:table :paginate="$this->leaders">
                        <flux:table.columns>
                            <flux:table.column>{{ __('Portrait') }}</flux:table.column>
                            <flux:table.column sortable :sorted="$sort === 'name'" :direction="$direction" wire:click="sortBy('name')">{{ __('Name') }}</flux:table.column>
                            <flux:table.column>{{ __('Positions') }}</flux:table.column>
                            <flux:table.column class="hidden lg:table-cell">{{ __('Primary title') }}</flux:table.column>
                            <flux:table.column sortable :sorted="$sort === 'status'" :direction="$direction" wire:click="sortBy('status')">{{ __('Status') }}</flux:table.column>
                            <flux:table.column sortable :sorted="$sort === 'visibility'" :direction="$direction" wire:click="sortBy('visibility')" class="hidden md:table-cell">{{ __('Visibility') }}</flux:table.column>
                            <flux:table.column sortable :sorted="$sort === 'order'" :direction="$direction" wire:click="sortBy('order')" class="hidden xl:table-cell">{{ __('Order') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($this->leaders as $leader)
                                @php
                                    $primaryAssignment = $leader->currentLeadershipAssignments->firstWhere('is_primary', true)
                                        ?? $leader->currentLeadershipAssignments->first();
                                    $initials = Str::upper(Str::substr($leader->first_name, 0, 1).Str::substr($leader->last_name, 0, 1));
                                @endphp

                                <flux:table.row :key="$leader->id" wire:key="leader-row-{{ $leader->id }}">
                                    <flux:table.cell>
                                        <flux:avatar
                                            :src="$leader->photo_path ? Storage::disk('public')->url($leader->photo_path) : null"
                                            :initials="$initials"
                                            size="sm"
                                        />
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="min-w-40">
                                            <p class="font-semibold text-slate-900 dark:text-white">{{ $leader->full_name }}</p>
                                            <p class="text-xs text-slate-500 dark:text-zinc-400">{{ $leader->slug }}</p>
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex max-w-64 flex-wrap gap-1.5">
                                            @foreach ($leader->currentLeadershipAssignments as $assignment)
                                                <flux:badge size="sm" color="zinc" wire:key="leader-{{ $leader->id }}-position-{{ $assignment->id }}">
                                                    {{ $assignment->position->name }}
                                                </flux:badge>
                                            @endforeach
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell class="hidden lg:table-cell">
                                        {{ $primaryAssignment?->display_title ?: $primaryAssignment?->position?->name ?: '—' }}
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex flex-wrap gap-1.5">
                                            <flux:badge :color="$leader->currentLeadershipAssignments->isNotEmpty() ? 'green' : 'amber'" size="sm">
                                                {{ $leader->currentLeadershipAssignments->isNotEmpty() ? __('Current') : __('Former') }}
                                            </flux:badge>
                                            @if (! $leader->is_active)
                                                <flux:badge color="zinc" size="sm">{{ __('Inactive profile') }}</flux:badge>
                                            @endif
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell class="hidden md:table-cell">
                                        <flux:badge :color="$leader->is_public ? 'green' : 'amber'" size="sm">
                                            {{ $leader->is_public ? __('Public') : __('Hidden') }}
                                        </flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell class="hidden xl:table-cell">{{ $leader->display_order ?? '—' }}</flux:table.cell>
                                    <flux:table.cell align="end">
                                        <div class="flex justify-end gap-1">
                                            @can('update', $leader)
                                                <flux:button :href="route('leadership.edit', $leader)" variant="ghost" size="sm" icon="pencil-square" :aria-label="__('Edit :name', ['name' => $leader->full_name])" wire:navigate />
                                                <flux:button wire:click="toggleActive({{ $leader->id }})" variant="ghost" size="sm" :icon="$leader->is_active ? 'pause' : 'play'" :aria-label="__('Toggle active status for :name', ['name' => $leader->full_name])" />
                                            @endcan
                                            @can('publish', $leader)
                                                <flux:button wire:click="toggleVisibility({{ $leader->id }})" variant="ghost" size="sm" :icon="$leader->is_public ? 'eye-slash' : 'eye'" :aria-label="__('Toggle visibility for :name', ['name' => $leader->full_name])" />
                                            @endcan
                                            @can('delete', $leader)
                                                <flux:button
                                                    wire:click="delete({{ $leader->id }})"
                                                    wire:confirm="{{ __('Delete :name and all leadership assignments? This cannot be undone.', ['name' => $leader->full_name]) }}"
                                                    variant="ghost"
                                                    size="sm"
                                                    icon="trash"
                                                    class="text-red-600 hover:text-red-700 dark:text-red-400"
                                                    :aria-label="__('Delete :name', ['name' => $leader->full_name])"
                                                />
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
