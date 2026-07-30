<?php

use App\Actions\Ministries\ChangeMinistryStatus;
use App\Actions\Ministries\DeleteMinistry;
use App\MinistryStatus;
use App\Models\Ministry;
use App\Models\Person;
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

new #[Title('Ministries')] class extends Component
{
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $status = '';
    #[Url] public string $featured = '';
    #[Url] public string $leader = '';
    #[Url] public string $sort = 'display_order';
    #[Url] public string $direction = 'asc';
    public int $perPage = 15;
    public bool $showConfirmModal = false;
    public ?int $targetMinistryId = null;
    public string $pendingAction = '';

    public function mount(): void { Gate::authorize('viewAny', Ministry::class); }
    public function updated(string $property): void { if (! in_array($property, ['showConfirmModal', 'targetMinistryId', 'pendingAction'], true)) $this->resetPage(); }

    #[Computed]
    public function ministries(): LengthAwarePaginator
    {
        $sort = in_array($this->sort, ['display_order', 'name', 'status', 'updated_at'], true) ? $this->sort : 'display_order';
        $direction = $this->direction === 'desc' ? 'desc' : 'asc';

        return Ministry::query()->withTrashed()
            ->select(['id', 'name', 'slug', 'logo', 'featured_image', 'meeting_day', 'meeting_time', 'meeting_location', 'status', 'is_featured', 'display_order', 'updated_at', 'deleted_at'])
            ->with(['leaders:id,title,first_name,middle_name,last_name'])->withCount('events')
            ->when($this->search !== '', fn (Builder $query): Builder => $query->search($this->search))
            ->when($this->status !== '', fn (Builder $query): Builder => $this->status === 'deleted' ? $query->onlyTrashed() : $query->where('status', $this->status))
            ->when($this->featured !== '', fn (Builder $query): Builder => $query->where('is_featured', $this->featured === '1'))
            ->when($this->leader !== '', fn (Builder $query): Builder => $query->whereHas('leaders', fn (Builder $leaders): Builder => $leaders->whereKey($this->leader)))
            ->orderBy($sort, $direction)->orderBy('name')->paginate($this->perPage);
    }

    #[Computed] public function leaders() { return Person::query()->leaders()->orderBy('last_name')->orderBy('first_name')->get(['id', 'title', 'first_name', 'middle_name', 'last_name']); }
    #[Computed] public function stats(): array { return ['total' => Ministry::withTrashed()->count(), 'published' => Ministry::query()->published()->count(), 'featured' => Ministry::query()->featured()->count()]; }

    public function clearFilters(): void { $this->reset(['search', 'status', 'featured', 'leader']); $this->resetPage(); }

    public function confirm(int $id, string $action): void
    {
        $ministry = Ministry::withTrashed()->findOrFail($id);
        $ability = match ($action) {
            'delete' => 'delete', 'restore' => 'restore', 'force-delete' => 'forceDelete',
            'publish', 'draft', 'inactive' => 'publish', 'feature' => 'feature', default => abort(404),
        };
        Gate::authorize($ability, $ministry);
        $this->targetMinistryId = $id; $this->pendingAction = $action; $this->showConfirmModal = true;
    }

    public function executeConfirmed(DeleteMinistry $delete, ChangeMinistryStatus $status): void
    {
        $ministry = Ministry::withTrashed()->findOrFail($this->targetMinistryId);
        $actor = Auth::user();
        match ($this->pendingAction) {
            'delete' => $delete->delete($actor, $ministry),
            'restore' => $delete->restore($actor, $ministry),
            'force-delete' => $delete->forceDelete($actor, $ministry),
            'publish' => $status->publish($actor, $ministry),
            'draft' => $status->draft($actor, $ministry),
            'inactive' => $status->inactive($actor, $ministry),
            'feature' => $status->toggleFeatured($actor, $ministry),
            default => abort(404),
        };
        $this->reset(['showConfirmModal', 'targetMinistryId', 'pendingAction']);
        unset($this->ministries, $this->stats);
        Flux::toast(variant: 'success', text: __('Ministry action completed successfully.'));
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs><flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item><flux:breadcrumbs.item>{{ __('Ministries') }}</flux:breadcrumbs.item></flux:breadcrumbs>
    <x-admin.page-header :title="__('Ministries')" :description="__('Manage church ministries, departments, groups, fellowships, and their leadership.')" :eyebrow="__('Content management')"><x-slot:actions>@can('create', Ministry::class)<flux:button :href="route('ministries.create')" variant="primary" icon="plus" wire:navigate>{{ __('Create ministry') }}</flux:button>@endcan</x-slot:actions></x-admin.page-header>
    <div class="grid gap-4 sm:grid-cols-3"><x-admin.stat-card :label="__('Total ministries')" :value="$this->stats['total']" :caption="__('Including deleted records')" icon="user-group" /><x-admin.stat-card :label="__('Published')" :value="$this->stats['published']" :caption="__('Visible on the website')" icon="globe-alt" /><x-admin.stat-card :label="__('Featured')" :value="$this->stats['featured']" :caption="__('Highlighted ministries')" icon="star" /></div>
    <section class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
        <div class="space-y-4 border-b border-slate-100 p-4 sm:p-6 dark:border-zinc-800">
            <flux:input wire:model.live.debounce.350ms="search" icon="magnifying-glass" :label="__('Search ministries')" />
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                <flux:select wire:model.live="status" :label="__('Status')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach (MinistryStatus::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach<flux:select.option value="deleted">{{ __('Deleted') }}</flux:select.option></flux:select>
                <flux:select wire:model.live="featured" :label="__('Featured')"><flux:select.option value="">{{ __('All') }}</flux:select.option><flux:select.option value="1">{{ __('Featured') }}</flux:select.option><flux:select.option value="0">{{ __('Not featured') }}</flux:select.option></flux:select>
                <flux:select wire:model.live="leader" :label="__('Leader')"><flux:select.option value="">{{ __('All leaders') }}</flux:select.option>@foreach ($this->leaders as $person)<flux:select.option :value="$person->id">{{ $person->full_name }}</flux:select.option>@endforeach</flux:select>
                <flux:select wire:model.live="sort" :label="__('Sort by')"><flux:select.option value="display_order">{{ __('Display order') }}</flux:select.option><flux:select.option value="name">{{ __('Name') }}</flux:select.option><flux:select.option value="updated_at">{{ __('Last updated') }}</flux:select.option></flux:select>
                <flux:select wire:model.live="direction" :label="__('Direction')"><flux:select.option value="asc">{{ __('Ascending') }}</flux:select.option><flux:select.option value="desc">{{ __('Descending') }}</flux:select.option></flux:select>
                <div class="flex items-end"><flux:button type="button" variant="ghost" icon="x-mark" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button></div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-[70rem] w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-zinc-800/60 dark:text-zinc-400"><tr><th class="px-6 py-3">{{ __('Ministry') }}</th><th class="px-4 py-3">{{ __('Primary leader') }}</th><th class="px-4 py-3">{{ __('Meeting') }}</th><th class="px-4 py-3">{{ __('Events') }}</th><th class="px-4 py-3">{{ __('Status') }}</th><th class="px-4 py-3">{{ __('Order') }}</th><th class="px-4 py-3">{{ __('Updated') }}</th><th class="px-6 py-3 text-right">{{ __('Actions') }}</th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zinc-800">
                @forelse ($this->ministries as $ministry)
                    @php($primary = $ministry->leaders->firstWhere('pivot.is_primary', true) ?? $ministry->leaders->first())
                    <tr wire:key="ministry-{{ $ministry->id }}"><td class="px-6 py-4"><div class="flex items-center gap-3">@if ($ministry->logoUrl() || $ministry->imageUrl())<img src="{{ $ministry->logoUrl() ?? $ministry->imageUrl() }}" alt="" class="size-10 rounded-lg object-cover">@endif<div><p class="font-semibold">{{ $ministry->name }}</p>@if ($ministry->is_featured)<flux:badge color="amber" size="sm">{{ __('Featured') }}</flux:badge>@endif</div></div></td><td class="px-4 py-4">{{ $primary?->full_name ?? __('Unassigned') }}</td><td class="px-4 py-4">{{ collect([$ministry->meeting_day, $ministry->meeting_time?->format('g:i A')])->filter()->implode(' · ') ?: __('Not set') }}</td><td class="px-4 py-4">{{ $ministry->events_count }}</td><td class="px-4 py-4"><flux:badge :color="$ministry->status === MinistryStatus::Published ? 'green' : ($ministry->status === MinistryStatus::Inactive ? 'red' : 'zinc')">{{ $ministry->trashed() ? __('Deleted') : $ministry->status->label() }}</flux:badge></td><td class="px-4 py-4">{{ $ministry->display_order }}</td><td class="px-4 py-4">{{ $ministry->updated_at->diffForHumans() }}</td>
                    <td class="px-6 py-4">
                        <div class="flex justify-end gap-2">
                            @if (! $ministry->trashed())
                                <flux:button size="sm" :href="route('ministries.show', $ministry)" icon="eye" wire:navigate />
                                <flux:button size="sm" :href="route('ministries.edit', $ministry)" icon="pencil-square" wire:navigate />
                                @can('publish', $ministry)
                                    <flux:button size="sm" icon="globe-alt" wire:click="confirm({{ $ministry->id }}, '{{ $ministry->status === MinistryStatus::Published ? 'draft' : 'publish' }}')" />
                                @endcan
                                @can('feature', $ministry)
                                    <flux:button size="sm" icon="star" wire:click="confirm({{ $ministry->id }}, 'feature')" />
                                @endcan
                                @can('delete', $ministry)
                                    <flux:button size="sm" variant="danger" icon="trash" wire:click="confirm({{ $ministry->id }}, 'delete')" />
                                @endcan
                            @else
                                @can('restore', $ministry)
                                    <flux:button size="sm" icon="arrow-path" wire:click="confirm({{ $ministry->id }}, 'restore')" />
                                @endcan
                                @can('forceDelete', $ministry)
                                    <flux:button size="sm" variant="danger" icon="trash" wire:click="confirm({{ $ministry->id }}, 'force-delete')" />
                                @endcan
                            @endif
                        </div>
                    </td>
                    </tr>
                @empty <tr><td colspan="8"><x-admin.empty-state icon="user-group" :title="__('No ministries found')" :description="__('Create a ministry or clear the current filters.')" /></td></tr>@endforelse
                </tbody>
            </table>
        </div>
        @if ($this->ministries->hasPages())<div class="border-t border-slate-100 px-6 py-4 dark:border-zinc-800">{{ $this->ministries->links() }}</div>@endif
    </section>
    <flux:modal wire:model="showConfirmModal" class="max-w-md"><flux:heading size="lg">{{ __('Confirm ministry action') }}</flux:heading><flux:text class="mt-2">{{ __('This action will be applied immediately. Continue?') }}</flux:text><div class="mt-6 flex justify-end gap-3"><flux:button wire:click="$set('showConfirmModal', false)">{{ __('Cancel') }}</flux:button><flux:button variant="danger" wire:click="executeConfirmed">{{ __('Continue') }}</flux:button></div></flux:modal>
</div>
