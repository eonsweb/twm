<?php

use App\Actions\PrayerRequests\ChangePrayerRequestWorkflow;
use App\Actions\PrayerRequests\DeletePrayerRequest;
use App\Models\PrayerRequest;
use App\Models\User;
use App\PrayerRequestCategory;
use App\PrayerRequestPriority;
use App\PrayerRequestStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Prayer Requests')] class extends Component
{
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $status = '';
    #[Url] public string $priority = '';
    #[Url] public string $category = '';
    #[Url] public string $assignment = '';
    public int $perPage = 20;
    /** @var list<int|string> */
    public array $selected = [];
    public string $bulkAction = '';
    public string $bulkValue = '';

    public function mount(): void { Gate::authorize('viewAny', PrayerRequest::class); }
    public function updated(string $property): void { if ($property !== 'perPage') { $this->resetPage(); } }
    public function clearFilters(): void { $this->reset(['search', 'status', 'priority', 'category', 'assignment']); $this->resetPage(); }

    public function applyBulk(ChangePrayerRequestWorkflow $workflow, DeletePrayerRequest $delete): void
    {
        $this->validate(['selected' => ['required', 'array', 'min:1', 'max:100'], 'selected.*' => ['integer', 'distinct'], 'bulkAction' => ['required', 'in:status,priority,assign,archive,spam,restore']]);
        $actor = auth()->user();
        foreach (PrayerRequest::withTrashed()->whereKey($this->selected)->get() as $request) {
            match ($this->bulkAction) {
                'status' => $workflow->changeStatus($actor, $request, PrayerRequestStatus::from($this->bulkValue)),
                'priority' => $workflow->changePriority($actor, $request, PrayerRequestPriority::from($this->bulkValue)),
                'assign' => $workflow->assign($actor, $request, filled($this->bulkValue) ? User::findOrFail((int) $this->bulkValue) : null),
                'archive' => $workflow->changeStatus($actor, $request, PrayerRequestStatus::Archived),
                'spam' => $workflow->changeStatus($actor, $request, PrayerRequestStatus::Spam),
                'restore' => $delete->restore($actor, $request),
            };
        }
        $this->reset(['selected', 'bulkAction', 'bulkValue']);
        unset($this->requests, $this->stats);
    }

    public function assignees()
    {
        return User::query()->where('account_status', 'active')->permission('prayer-requests.update')->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function requests(): LengthAwarePaginator
    {
        $canViewContact = auth()->user()?->can('viewContactDetails', new PrayerRequest) ?? false;
        return PrayerRequest::query()->withTrashed()
            ->select(array_filter(['id', 'reference_number', $canViewContact ? 'name' : null, 'subject', 'category', 'status', 'priority', 'privacy_level', 'assigned_to', 'is_anonymous', 'is_published', 'created_at', 'updated_at', 'deleted_at']))
            ->with('assignee:id,name')
            ->when($this->search !== '', function (Builder $query) use ($canViewContact): void {
                $term = '%'.str($this->search)->squish().'%';
                $query->where(function (Builder $query) use ($term, $canViewContact): void {
                    $query->where('reference_number', 'like', $term)->orWhere('subject', 'like', $term)->orWhere('request', 'like', $term);
                    if ($canViewContact) { $query->orWhere('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term); }
                });
            })
            ->when($this->status !== '', fn (Builder $query): Builder => $this->status === 'deleted' ? $query->onlyTrashed() : $query->where('status', $this->status))
            ->when($this->priority !== '', fn (Builder $query): Builder => $query->where('priority', $this->priority))
            ->when($this->category !== '', fn (Builder $query): Builder => $query->where('category', $this->category))
            ->when($this->assignment === 'mine', fn (Builder $query): Builder => $query->where('assigned_to', auth()->id()))
            ->when($this->assignment === 'unassigned', fn (Builder $query): Builder => $query->whereNull('assigned_to'))
            ->latest()->paginate($this->perPage);
    }

    #[Computed]
    public function stats(): array
    {
        return ['new' => PrayerRequest::query()->new()->count(), 'active' => PrayerRequest::query()->active()->count(), 'urgent' => PrayerRequest::query()->urgent()->active()->count(), 'answered' => PrayerRequest::query()->answered()->count()];
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs><flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item><flux:breadcrumbs.item>{{ __('Prayer requests') }}</flux:breadcrumbs.item></flux:breadcrumbs>
    <x-admin.page-header :title="__('Prayer requests')" :description="__('Review confidential requests, coordinate pastoral care, and publish only separately approved stories.')" :eyebrow="__('Care management')"><x-slot:actions>@can('create', PrayerRequest::class)<flux:button :href="route('prayer-requests.create')" variant="primary" icon="plus" wire:navigate>{{ __('Add request') }}</flux:button>@endcan</x-slot:actions></x-admin.page-header>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><x-admin.stat-card :label="__('New')" :value="$this->stats['new']" :caption="__('Awaiting review')" icon="inbox"/><x-admin.stat-card :label="__('Active')" :value="$this->stats['active']" :caption="__('Open care work')" icon="heart"/><x-admin.stat-card :label="__('Urgent')" :value="$this->stats['urgent']" :caption="__('Needs attention')" icon="exclamation-triangle"/><x-admin.stat-card :label="__('Answered')" :value="$this->stats['answered']" :caption="__('Recorded testimonies')" icon="sparkles"/></div>
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
        <div class="grid gap-4 border-b p-4 md:grid-cols-2 xl:grid-cols-6 dark:border-zinc-800"><div class="xl:col-span-2"><flux:input wire:model.live.debounce.350ms="search" icon="magnifying-glass" :label="__('Search')" /></div><flux:select wire:model.live="status" :label="__('Status')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach(PrayerRequestStatus::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach<flux:select.option value="deleted">{{ __('Deleted') }}</flux:select.option></flux:select><flux:select wire:model.live="priority" :label="__('Priority')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach(PrayerRequestPriority::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select><flux:select wire:model.live="category" :label="__('Category')"><flux:select.option value="">{{ __('All') }}</flux:select.option>@foreach(PrayerRequestCategory::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select><flux:select wire:model.live="assignment" :label="__('Assignment')"><flux:select.option value="">{{ __('All') }}</flux:select.option><flux:select.option value="mine">{{ __('Assigned to me') }}</flux:select.option><flux:select.option value="unassigned">{{ __('Unassigned') }}</flux:select.option></flux:select></div>
        @if($selected)<form wire:submit="applyBulk" class="flex flex-wrap items-end gap-3 border-b bg-slate-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/50"><flux:select wire:model.live="bulkAction" :label="__('Bulk action')"><flux:select.option value="">{{ __('Choose') }}</flux:select.option><flux:select.option value="status">{{ __('Change status') }}</flux:select.option><flux:select.option value="priority">{{ __('Change priority') }}</flux:select.option><flux:select.option value="assign">{{ __('Assign') }}</flux:select.option><flux:select.option value="archive">{{ __('Archive') }}</flux:select.option><flux:select.option value="spam">{{ __('Mark spam') }}</flux:select.option><flux:select.option value="restore">{{ __('Restore') }}</flux:select.option></flux:select>@if($bulkAction === 'status')<flux:select wire:model="bulkValue" :label="__('Status')">@foreach(PrayerRequestStatus::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select>@elseif($bulkAction === 'priority')<flux:select wire:model="bulkValue" :label="__('Priority')">@foreach(PrayerRequestPriority::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select>@elseif($bulkAction === 'assign')<flux:select wire:model="bulkValue" :label="__('Assignee')"><flux:select.option value="">{{ __('Unassigned') }}</flux:select.option>@foreach($this->assignees() as $user)<flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>@endforeach</flux:select>@endif<flux:button type="submit" variant="primary">{{ __('Apply to :count', ['count' => count($selected)]) }}</flux:button></form>@endif
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-slate-600 dark:bg-zinc-800/70 dark:text-zinc-300"><tr><th class="px-5 py-3"></th><th class="px-5 py-3">{{ __('Reference') }}</th><th class="px-5 py-3">{{ __('Requester') }}</th><th class="px-5 py-3">{{ __('Subject') }}</th><th class="px-5 py-3">{{ __('Workflow') }}</th><th class="px-5 py-3">{{ __('Assigned') }}</th><th class="px-5 py-3"></th></tr></thead><tbody class="divide-y dark:divide-zinc-800">@forelse($this->requests as $request)<tr wire:key="prayer-request-{{ $request->id }}"><td class="px-5 py-4"><flux:checkbox wire:model.live="selected" :value="$request->id" /></td><td class="px-5 py-4 font-mono text-xs">{{ $request->reference_number }}</td><td class="px-5 py-4">@can('viewContactDetails', $request){{ $request->requesterLabel() }}@else{{ $request->is_anonymous ? __('Anonymous') : __('Confidential') }}@endcan</td><td class="max-w-sm px-5 py-4"><p class="truncate font-medium">{{ $request->subject }}</p><p class="mt-1 text-xs text-slate-500">{{ $request->category?->label() ?? __('Uncategorized') }}</p></td><td class="px-5 py-4"><div class="flex gap-2"><flux:badge :color="$request->status->color()">{{ $request->status->label() }}</flux:badge><flux:badge :color="$request->priority->color()">{{ $request->priority->label() }}</flux:badge></div></td><td class="px-5 py-4">{{ $request->assignee?->name ?? __('Unassigned') }}</td><td class="px-5 py-4 text-right">@unless($request->trashed())<flux:button size="sm" :href="route('prayer-requests.show', $request)" wire:navigate>{{ __('Open') }}</flux:button>@endunless</td></tr>@empty<tr><td colspan="7" class="p-8 text-center text-slate-500">{{ __('No prayer requests match these filters.') }}</td></tr>@endforelse</tbody></table></div>
        @if($this->requests->hasPages())<div class="border-t p-4 dark:border-zinc-800">{{ $this->requests->links() }}</div>@endif
    </section>
</div>
