<?php

use App\Models\Person;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Leadership ordering')] class extends Component
{
    public function mount(): void
    {
        Gate::authorize('reorder', Person::class);
    }

    #[Computed]
    public function leaders()
    {
        return Person::query()
            ->whereHas('currentLeadershipAssignments')
            ->withMin('currentLeadershipAssignments as display_order', 'sort_order')
            ->with([
                'currentLeadershipAssignments' => fn ($query) => $query
                    ->with('position:id,name')
                    ->orderByDesc('is_primary'),
            ])
            ->orderBy('display_order')
            ->orderBy('last_name')
            ->get();
    }

    public function reorder(int $personId, int $position): void
    {
        Gate::authorize('reorder', Person::class);

        $orderedIds = $this->leaders->pluck('id')->reject(
            fn (int $id): bool => $id === $personId,
        )->values();

        $orderedIds->splice(max(0, min($position, $orderedIds->count())), 0, [$personId]);

        DB::transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $index => $orderedPersonId) {
                Person::query()
                    ->findOrFail($orderedPersonId)
                    ->currentLeadershipAssignments()
                    ->update(['sort_order' => ($index + 1) * 10]);
            }
        });

        unset($this->leaders);
    }
};
?>

<div class="mx-auto w-full max-w-5xl space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('leadership.index')" wire:navigate>{{ __('Leadership') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Ordering') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-admin.page-header
        :title="__('Leadership ordering')"
        :description="__('Drag leaders into the order used by future public leadership pages.')"
        :eyebrow="__('Leadership')"
    >
        <x-slot:actions>
            <flux:button :href="route('leadership.index')" variant="outline" icon="arrow-left" wire:navigate>
                {{ __('Back to leadership') }}
            </flux:button>
        </x-slot:actions>
    </x-admin.page-header>

    <section class="rounded-xl border border-slate-200/80 bg-white p-4 shadow-admin-panel sm:p-5 dark:border-zinc-800 dark:bg-zinc-900">
        @if ($this->leaders->isEmpty())
            <x-admin.empty-state icon="bars-3" :title="__('Nothing to order')" :description="__('Add a leader with a current assignment first.')" />
        @else
            <div wire:sort="reorder" class="space-y-3">
                @foreach ($this->leaders as $index => $leader)
                    @php
                        $primaryAssignment = $leader->currentLeadershipAssignments->firstWhere('is_primary', true)
                            ?? $leader->currentLeadershipAssignments->first();
                        $initials = Str::upper(Str::substr($leader->first_name, 0, 1).Str::substr($leader->last_name, 0, 1));
                    @endphp

                    <article
                        wire:key="ordered-leader-{{ $leader->id }}"
                        wire:sort:item="{{ $leader->id }}"
                        class="flex items-center gap-4 rounded-xl border border-slate-200 bg-slate-50 p-3 transition dark:border-zinc-700 dark:bg-zinc-800/70"
                    >
                        <button type="button" wire:sort:handle class="cursor-grab rounded-lg p-2 text-slate-400 hover:bg-white hover:text-slate-700 active:cursor-grabbing dark:hover:bg-zinc-700 dark:hover:text-white" :aria-label="__('Reorder :name', ['name' => $leader->full_name])">
                            <flux:icon.bars-3 class="size-5" />
                        </button>

                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-church-maroon-100 text-xs font-bold text-church-maroon-900 dark:bg-church-maroon-900 dark:text-church-gold-300">
                            {{ $index + 1 }}
                        </span>

                        <flux:avatar
                            :src="$leader->photo_path ? Storage::disk('public')->url($leader->photo_path) : null"
                            :initials="$initials"
                            size="sm"
                        />

                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-slate-900 dark:text-white">{{ $leader->full_name }}</p>
                            <p class="truncate text-sm text-slate-500 dark:text-zinc-400">
                                {{ $primaryAssignment?->display_title ?: $primaryAssignment?->position?->name }}
                            </p>
                        </div>

                        <span class="hidden text-xs font-medium text-slate-400 sm:block">{{ __('Order :order', ['order' => $leader->display_order]) }}</span>
                    </article>
                @endforeach
            </div>
        @endif

        <div wire:loading.flex wire:target="reorder" class="mt-4 items-center gap-2 text-sm text-church-maroon-700 dark:text-church-gold-400">
            <flux:icon.arrow-path class="size-4 animate-spin" />
            {{ __('Saving order…') }}
        </div>
    </section>
</div>
