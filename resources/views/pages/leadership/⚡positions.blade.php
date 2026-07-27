<?php

use App\Models\LeadershipPosition;
use App\PermissionName;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Leadership positions')] class extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public int $rank = 0;

    public bool $isActive = true;

    public function mount(): void
    {
        Gate::authorize('viewAny', LeadershipPosition::class);
    }

    #[Computed]
    public function positions()
    {
        return LeadershipPosition::query()
            ->withCount('leadershipAssignments')
            ->orderBy('rank')
            ->orderBy('name')
            ->get();
    }

    public function edit(LeadershipPosition $leadershipPosition): void
    {
        Gate::authorize('update', $leadershipPosition);

        $this->editingId = $leadershipPosition->id;
        $this->name = $leadershipPosition->name;
        $this->slug = $leadershipPosition->slug;
        $this->description = $leadershipPosition->description ?? '';
        $this->rank = $leadershipPosition->rank;
        $this->isActive = $leadershipPosition->is_active;
        $this->resetValidation();
    }

    public function save(): void
    {
        $leadershipPosition = $this->editingId !== null
            ? LeadershipPosition::query()->findOrFail($this->editingId)
            : null;

        Gate::authorize($leadershipPosition ? 'update' : 'create', $leadershipPosition ?? LeadershipPosition::class);

        $this->slug = Str::slug($this->slug !== '' ? $this->slug : $this->name);

        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(LeadershipPosition::class, 'name')->ignore($leadershipPosition?->id),
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash:ascii',
                Rule::unique(LeadershipPosition::class, 'slug')->ignore($leadershipPosition?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'rank' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'isActive' => ['boolean'],
        ]);

        LeadershipPosition::query()->updateOrCreate(
            ['id' => $leadershipPosition?->id],
            [
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'description' => $validated['description'] ?: null,
                'rank' => $validated['rank'],
                'is_active' => $validated['isActive'],
            ],
        );

        $this->resetForm();
        unset($this->positions);
        Flux::toast(variant: 'success', text: __('Leadership position saved.'));
    }

    public function delete(LeadershipPosition $leadershipPosition): void
    {
        Gate::authorize('delete', $leadershipPosition);

        $leadershipPosition->delete();
        unset($this->positions);

        Flux::toast(variant: 'success', text: __('Leadership position deleted.'));
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'slug', 'description', 'rank']);
        $this->isActive = true;
        $this->resetValidation();
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('leadership.index')" wire:navigate>{{ __('Leadership') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Positions') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-admin.page-header
        :title="__('Leadership positions')"
        :description="__('Define the public ministry hierarchy independently from administration roles.')"
        :eyebrow="__('Leadership')"
    >
        <x-slot:actions>
            <flux:button :href="route('leadership.index')" variant="outline" icon="arrow-left" wire:navigate>
                {{ __('Back to leadership') }}
            </flux:button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-6 xl:grid-cols-[minmax(20rem,0.7fr)_minmax(0,1.3fr)]">
        @canany([PermissionName::LeadershipCreate->value, PermissionName::LeadershipUpdate->value])
            <form wire:submit="save" class="h-fit space-y-5 rounded-xl border border-slate-200/80 bg-white p-5 shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
                <div>
                    <flux:heading size="lg">{{ $editingId ? __('Edit position') : __('Add position') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Lower ranks appear earlier in hierarchy filters.') }}</flux:text>
                </div>

                <flux:input wire:model="name" :label="__('Name')" required />
                <flux:input wire:model="slug" :label="__('Slug')" :placeholder="__('Generated from the name when blank')" />
                <flux:textarea wire:model="description" :label="__('Description')" rows="4" />
                <flux:input wire:model="rank" :label="__('Hierarchy rank')" type="number" min="0" required />
                <flux:switch wire:model="isActive" :label="__('Active position')" />

                <div class="flex justify-end gap-2">
                    @if ($editingId)
                        <flux:button type="button" variant="ghost" wire:click="resetForm">{{ __('Cancel') }}</flux:button>
                    @endif
                    <flux:button type="submit" variant="primary" icon="check">{{ __('Save position') }}</flux:button>
                </div>
            </form>
        @endcanany

        <section class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
            @if ($this->positions->isEmpty())
                <x-admin.empty-state icon="briefcase" :title="__('No positions found')" :description="__('Add the first ministry position to begin the hierarchy.')" />
            @else
                <flux:table class="px-4 sm:px-6 md:px-8">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Position') }}</flux:table.column>
                        <flux:table.column>{{ __('Rank') }}</flux:table.column>
                        <flux:table.column>{{ __('Assignments') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->positions as $leadershipPosition)
                            <flux:table.row :key="$leadershipPosition->id" wire:key="position-row-{{ $leadershipPosition->id }}">
                                <flux:table.cell>
                                    <p class="font-semibold text-slate-900 dark:text-white">{{ $leadershipPosition->name }}</p>
                                    <p class="text-xs text-slate-500 dark:text-zinc-400">{{ $leadershipPosition->slug }}</p>
                                </flux:table.cell>
                                <flux:table.cell>{{ $leadershipPosition->rank }}</flux:table.cell>
                                <flux:table.cell>{{ $leadershipPosition->leadership_assignments_count }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$leadershipPosition->is_active ? 'green' : 'zinc'" size="sm">
                                        {{ $leadershipPosition->is_active ? __('Active') : __('Inactive') }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    <div class="flex justify-end gap-1">
                                        @can('update', $leadershipPosition)
                                            <flux:button wire:click="edit({{ $leadershipPosition->id }})" variant="ghost" size="sm" icon="pencil-square" :aria-label="__('Edit :position', ['position' => $leadershipPosition->name])" />
                                        @endcan
                                        @can('delete', $leadershipPosition)
                                            <flux:button
                                                wire:click="delete({{ $leadershipPosition->id }})"
                                                wire:confirm="{{ __('Delete the :position position?', ['position' => $leadershipPosition->name]) }}"
                                                variant="ghost"
                                                size="sm"
                                                icon="trash"
                                                class="text-red-600 dark:text-red-400"
                                                :aria-label="__('Delete :position', ['position' => $leadershipPosition->name])"
                                            />
                                        @endcan
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </section>
    </div>
</div>
