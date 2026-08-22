<?php

use App\Actions\ServiceSchedules\DeleteServiceSchedule;
use App\Actions\ServiceSchedules\SaveServiceSchedule;
use App\Models\ServiceSchedule;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Service Schedules')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    public int $perPage = 15;

    public bool $showDeleteModal = false;

    public ?int $targetScheduleId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', ServiceSchedule::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function schedules(): LengthAwarePaginator
    {
        return ServiceSchedule::query()
            ->select(['id', 'name', 'day_of_week', 'start_time', 'end_time', 'location', 'display_order', 'is_active', 'updated_at'])
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(fn (Builder $query): Builder => $query
                    ->where('name', 'like', $term)
                    ->orWhere('location', 'like', $term));
            })
            ->when($this->status !== '', fn (Builder $query): Builder => $query->where('is_active', $this->status === 'active'))
            ->ordered()
            ->paginate($this->perPage);
    }

    public function toggleActive(int $id, SaveServiceSchedule $saveServiceSchedule): void
    {
        $schedule = ServiceSchedule::query()->findOrFail($id);
        $saveServiceSchedule->toggleActive(Auth::user(), $schedule);
        unset($this->schedules);
        Flux::toast(variant: 'success', text: $schedule->is_active ? __('Service schedule activated.') : __('Service schedule deactivated.'));
    }

    public function confirmDelete(int $id): void
    {
        $schedule = ServiceSchedule::query()->findOrFail($id);
        Gate::authorize('delete', $schedule);
        $this->targetScheduleId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(DeleteServiceSchedule $deleteServiceSchedule): void
    {
        $schedule = ServiceSchedule::query()->findOrFail($this->targetScheduleId);
        $deleteServiceSchedule->handle(Auth::user(), $schedule);
        $this->reset(['showDeleteModal', 'targetScheduleId']);
        unset($this->schedules);
        Flux::toast(variant: 'success', text: __('Service schedule deleted.'));
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Service Schedules') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-admin.page-header :title="__('Service Schedules')" :description="__('Manage recurring weekly church programs shown on the website.')" :eyebrow="__('Content management')">
        <x-slot:actions>
            @can('create', ServiceSchedule::class)
                <flux:button :href="route('service-schedules.create')" variant="primary" icon="plus" wire:navigate>{{ __('Create Service Schedule') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <section class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
        <div class="grid gap-4 border-b border-slate-100 p-4 sm:grid-cols-[minmax(0,1fr)_14rem] sm:p-6 dark:border-zinc-800">
            <flux:input wire:model.live.debounce.350ms="search" icon="magnifying-glass" :label="__('Search schedules')" />
            <flux:select wire:model.live="status" :label="__('Status')">
                <flux:select.option value="">{{ __('All') }}</flux:select.option>
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
            </flux:select>
        </div>

        <div wire:loading.flex class="items-center gap-2 border-b border-slate-100 px-6 py-3 text-sm text-slate-500 dark:border-zinc-800">
            <flux:icon.arrow-path class="size-4 animate-spin" />
            {{ __('Updating service schedules…') }}
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[64rem] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                    <tr>
                        <th class="px-6 py-3">{{ __('Name') }}</th>
                        <th class="px-4 py-3">{{ __('Day') }}</th>
                        <th class="px-4 py-3">{{ __('Time') }}</th>
                        <th class="px-4 py-3">{{ __('Location') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                        <th class="px-4 py-3">{{ __('Display order') }}</th>
                        <th class="px-6 py-3 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                    @forelse ($this->schedules as $schedule)
                        <tr wire:key="service-schedule-row-{{ $schedule->id }}">
                            <td class="px-6 py-4 font-semibold text-slate-950 dark:text-white">{{ $schedule->name }}</td>
                            <td class="px-4 py-4">{{ $schedule->day_of_week }}</td>
                            <td class="px-4 py-4">{{ $schedule->formattedTimeRange() }}</td>
                            <td class="px-4 py-4">{{ $schedule->location ?: '—' }}</td>
                            <td class="px-4 py-4"><flux:badge :color="$schedule->is_active ? 'green' : 'zinc'">{{ $schedule->is_active ? __('Active') : __('Inactive') }}</flux:badge></td>
                            <td class="px-4 py-4">{{ $schedule->display_order }}</td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    @can('update', $schedule)
                                        <flux:button size="sm" :href="route('service-schedules.edit', $schedule)" icon="pencil-square" :aria-label="__('Edit :name', ['name' => $schedule->name])" wire:navigate />
                                        <flux:button size="sm" icon="power" wire:click="toggleActive({{ $schedule->id }})" :aria-label="$schedule->is_active ? __('Deactivate :name', ['name' => $schedule->name]) : __('Activate :name', ['name' => $schedule->name])" />
                                    @endcan
                                    @can('delete', $schedule)
                                        <flux:button size="sm" variant="danger" icon="trash" wire:click="confirmDelete({{ $schedule->id }})" :aria-label="__('Delete :name', ['name' => $schedule->name])" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-admin.empty-state icon="clock" :title="__('No service schedules found')" :description="__('Create a weekly schedule or clear the current filters.')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->schedules->hasPages())
            <div class="border-t border-slate-100 px-6 py-4 dark:border-zinc-800">{{ $this->schedules->links() }}</div>
        @endif
    </section>

    <flux:modal wire:model="showDeleteModal" class="max-w-md">
        <form wire:submit="delete" class="space-y-5">
            <flux:heading size="lg">{{ __('Delete service schedule?') }}</flux:heading>
            <flux:text>{{ __('This removes the recurring program from administration and the public homepage.') }}</flux:text>
            <div class="flex justify-end gap-3">
                <flux:button type="button" variant="ghost" wire:click="$set('showDeleteModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="danger" wire:loading.attr="disabled" wire:target="delete">{{ __('Delete') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
