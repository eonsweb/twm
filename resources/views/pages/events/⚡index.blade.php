<?php

use App\Actions\Events\ChangeEventStatus;
use App\Actions\Events\DeleteEvent;
use App\Actions\Events\DuplicateEvent;
use App\EventStatus;
use App\Models\Event;
use App\Models\EventType;
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

new #[Title('Events')] class extends Component
{
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $eventType = '';
    #[Url] public string $status = '';
    #[Url] public string $date = '';
    #[Url] public string $timeframe = '';
    #[Url] public string $featured = '';
    #[Url] public string $sort = 'starts_at';
    #[Url] public string $direction = 'asc';
    public int $perPage = 15;
    public bool $showConfirmModal = false;
    public ?int $targetEventId = null;
    public string $pendingAction = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Event::class);
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['showConfirmModal', 'targetEventId', 'pendingAction'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function events(): LengthAwarePaginator
    {
        $sort = in_array($this->sort, ['starts_at', 'ends_at', 'title', 'status', 'created_at', 'updated_at'], true)
            ? $this->sort
            : 'starts_at';
        $direction = $this->direction === 'desc' ? 'desc' : 'asc';

        return Event::query()
            ->withTrashed()
            ->select([
                'id', 'event_type_id', 'created_by', 'title', 'slug', 'featured_image',
                'featured_image_id', 'icon',
                'location_type', 'venue_name', 'city', 'meeting_url', 'starts_at', 'ends_at',
                'timezone', 'schedule_type', 'is_all_day', 'is_recurring', 'recurrence_rule',
                'recurrence_interval', 'recurrence_days', 'recurrence_week_of_month', 'recurrence_month',
                'recurrence_day_of_month', 'recurrence_end_date', 'is_featured', 'is_active', 'sort_order', 'is_livestreamed', 'status',
                'published_at', 'updated_at', 'deleted_at',
            ])
            ->with(['eventType:id,name,color,icon', 'featuredImage:id,disk,path,media_type,visibility,status', 'creator:id,name'])
            ->when($this->search !== '', function (Builder $query): void {
                $search = '%'.trim($this->search).'%';
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', $search)
                        ->orWhere('short_description', 'like', $search)
                        ->orWhere('venue_name', 'like', $search)
                        ->orWhere('city', 'like', $search)
                        ->orWhereHas('eventType', fn (Builder $type): Builder => $type->where('name', 'like', $search));
                });
            })
            ->when($this->eventType !== '', fn (Builder $query): Builder => $query->where('event_type_id', $this->eventType))
            ->when($this->status !== '', function (Builder $query): Builder {
                return $this->status === 'deleted'
                    ? $query->onlyTrashed()
                    : $query->where('status', $this->status);
            })
            ->when($this->featured !== '', fn (Builder $query): Builder => $query->where('is_featured', $this->featured === '1'))
            ->when($this->timeframe === 'upcoming', fn (Builder $query): Builder => $query->upcoming())
            ->when($this->timeframe === 'past', fn (Builder $query): Builder => $query->past())
            ->when($this->date === 'today', fn (Builder $query): Builder => $query->today())
            ->when($this->date === 'week', fn (Builder $query): Builder => $query->whereBetween('starts_at', [now()->startOfWeek(), now()->endOfWeek()]))
            ->when($this->date === 'month', fn (Builder $query): Builder => $query->whereBetween('starts_at', [now()->startOfMonth(), now()->endOfMonth()]))
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->paginate($this->perPage);
    }

    #[Computed]
    public function eventTypes()
    {
        return EventType::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => Event::withTrashed()->count(),
            'upcoming' => Event::query()->upcoming()->count(),
            'published' => Event::query()->published()->count(),
        ];
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'eventType', 'status', 'date', 'timeframe', 'featured']);
        $this->resetPage();
    }

    public function confirm(int $eventId, string $action): void
    {
        $event = Event::withTrashed()->findOrFail($eventId);
        $ability = match ($action) {
            'delete' => 'delete',
            'restore' => 'restore',
            'duplicate' => 'duplicate',
            'publish', 'unpublish' => 'publish',
            'cancel' => 'cancel',
            'complete' => 'complete',
            default => abort(404),
        };
        Gate::authorize($ability, $event);
        $this->targetEventId = $eventId;
        $this->pendingAction = $action;
        $this->showConfirmModal = true;
    }

    public function executeConfirmed(
        DeleteEvent $deleteEvent,
        DuplicateEvent $duplicateEvent,
        ChangeEventStatus $changeStatus,
    ): void {
        $event = Event::withTrashed()->findOrFail($this->targetEventId);
        $actor = Auth::user();

        match ($this->pendingAction) {
            'delete' => $deleteEvent->delete($actor, $event),
            'restore' => $deleteEvent->restore($actor, $event),
            'duplicate' => $duplicateEvent->handle($actor, $event),
            'publish' => $changeStatus->publish($actor, $event),
            'unpublish' => $changeStatus->unpublish($actor, $event),
            'cancel' => $changeStatus->cancel($actor, $event),
            'complete' => $changeStatus->complete($actor, $event),
            default => abort(404),
        };

        $message = match ($this->pendingAction) {
            'publish' => __('Event published successfully.'),
            'cancel' => __('Event cancelled successfully.'),
            'delete' => __('Event deleted successfully.'),
            'restore' => __('Event restored successfully.'),
            default => __('Event action completed successfully.'),
        };
        $this->reset(['showConfirmModal', 'targetEventId', 'pendingAction']);
        unset($this->events, $this->stats);
        Flux::toast(variant: 'success', text: $message);
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Events') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <x-admin.page-header :title="__('Events')" :description="__('Create, schedule, publish, and manage church events across physical and online venues.')" :eyebrow="__('Content management')">
        <x-slot:actions>
            @can('viewAny', \App\Models\EventType::class)
                <flux:button :href="route('event-types.index')" icon="tag" wire:navigate>{{ __('Event types') }}</flux:button>
            @endcan
            @can('create', \App\Models\Event::class)
                <flux:button :href="route('events.create')" variant="primary" icon="plus" wire:navigate>{{ __('Create event') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-admin.stat-card :label="__('Total events')" :value="$this->stats['total']" :caption="__('Including deleted records')" icon="calendar-days" />
        <x-admin.stat-card :label="__('Upcoming')" :value="$this->stats['upcoming']" :caption="__('Scheduled for now or later')" icon="clock" />
        <x-admin.stat-card :label="__('Published')" :value="$this->stats['published']" :caption="__('Visible on the public website')" icon="globe-alt" />
    </div>

    <section class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900">
        <div class="space-y-4 border-b border-slate-100 p-4 sm:p-6 dark:border-zinc-800">
            <flux:input wire:model.live.debounce.350ms="search" icon="magnifying-glass" :label="__('Search events')" :placeholder="__('Title, description, type, venue, or city')" />
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                <flux:select wire:model.live="eventType" :label="__('Event type')"><flux:select.option value="">{{ __('All types') }}</flux:select.option>@foreach ($this->eventTypes as $type)<flux:select.option :value="$type->id">{{ $type->name }}</flux:select.option>@endforeach</flux:select>
                <flux:select wire:model.live="status" :label="__('Status')"><flux:select.option value="">{{ __('All statuses') }}</flux:select.option>@foreach (EventStatus::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach<flux:select.option value="deleted">{{ __('Deleted') }}</flux:select.option></flux:select>
                <flux:select wire:model.live="date" :label="__('Date range')"><flux:select.option value="">{{ __('Any date') }}</flux:select.option><flux:select.option value="today">{{ __('Today') }}</flux:select.option><flux:select.option value="week">{{ __('This week') }}</flux:select.option><flux:select.option value="month">{{ __('This month') }}</flux:select.option></flux:select>
                <flux:select wire:model.live="timeframe" :label="__('Timing')"><flux:select.option value="">{{ __('All') }}</flux:select.option><flux:select.option value="upcoming">{{ __('Upcoming / ongoing') }}</flux:select.option><flux:select.option value="past">{{ __('Past') }}</flux:select.option></flux:select>
                <flux:select wire:model.live="featured" :label="__('Featured')"><flux:select.option value="">{{ __('All') }}</flux:select.option><flux:select.option value="1">{{ __('Featured') }}</flux:select.option><flux:select.option value="0">{{ __('Not featured') }}</flux:select.option></flux:select>
                <flux:select wire:model.live="sort" :label="__('Sort by')"><flux:select.option value="starts_at">{{ __('Start date') }}</flux:select.option><flux:select.option value="title">{{ __('Title') }}</flux:select.option><flux:select.option value="status">{{ __('Status') }}</flux:select.option><flux:select.option value="updated_at">{{ __('Last updated') }}</flux:select.option></flux:select>
                <flux:select wire:model.live="direction" :label="__('Direction')"><flux:select.option value="asc">{{ __('Ascending') }}</flux:select.option><flux:select.option value="desc">{{ __('Descending') }}</flux:select.option></flux:select>
            </div>
            <div class="flex justify-end">
                <flux:button type="button" variant="ghost" icon="x-mark" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
            </div>
        </div>

        <div class="relative p-4 sm:p-6">
            <div wire:loading.flex class="absolute inset-0 z-10 items-center justify-center bg-white/75 backdrop-blur-sm dark:bg-zinc-900/75"><flux:icon.arrow-path class="size-5 animate-spin" /></div>
            @if ($this->events->isEmpty())
                <x-admin.empty-state icon="calendar-days" :title="__('No events found')" :description="__('Adjust the filters or create the first event.')" />
            @else
                <div class="hidden overflow-x-auto md:block">
                    <flux:table :paginate="$this->events">
                        <flux:table.columns>
                            <flux:table.column class="ps-4">{{ __('Event') }}</flux:table.column>
                            <flux:table.column>{{ __('Schedule') }}</flux:table.column>
                            <flux:table.column>{{ __('Next occurrence') }}</flux:table.column>
                            <flux:table.column>{{ __('Status') }}</flux:table.column>
                            <flux:table.column class="hidden xl:table-cell">{{ __('Creator / updated') }}</flux:table.column>
                            <flux:table.column align="end" class="pe-4">{{ __('Actions') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->events as $event)
                                <flux:table.row :key="$event->id" wire:key="event-row-{{ $event->id }}">
                                    <flux:table.cell class="ps-4">
                                        <div class="flex min-w-72 items-center gap-3">
                                            <div class="flex h-14 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-church-maroon-950 text-church-gold-400">
                                                @if ($event->imageUrl())<img src="{{ $event->imageUrl() }}" alt="" class="h-full w-full object-cover">@else<flux:icon :name="$event->effectiveIcon()" class="size-6" />@endif
                                            </div>
                                            <div><p class="font-semibold text-slate-950 dark:text-white">{{ $event->title }}</p><p class="text-xs text-slate-500">{{ $event->eventType?->name ?? __('Uncategorized') }}</p></div>
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell><p class="max-w-72">{{ $event->scheduleLabel() }}</p></flux:table.cell>
                                    <flux:table.cell>@if ($next = $event->nextOccurrence())<p>{{ $next->setTimezone($event->timezone)->format('j M Y') }}</p><p class="text-xs text-slate-500">{{ $next->setTimezone($event->timezone)->format('g:i A') }}</p>@else<span class="text-slate-500">{{ __('No future date') }}</span>@endif</flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex max-w-48 flex-wrap gap-1">
                                            <flux:badge :color="$event->deleted_at ? 'red' : match($event->status) { EventStatus::Published => 'green', EventStatus::Scheduled => 'blue', EventStatus::Cancelled => 'red', EventStatus::Completed => 'zinc', default => 'amber' }">{{ $event->deleted_at ? __('Deleted') : $event->status->label() }}</flux:badge>
                                            @if ($event->is_featured)<flux:badge color="amber">{{ __('Featured') }}</flux:badge>@endif
                                            @if ($event->is_livestreamed)<flux:badge color="blue">{{ __('Livestream') }}</flux:badge>@endif
                                            @if (! $event->is_active)<flux:badge color="zinc">{{ __('Inactive') }}</flux:badge>@endif
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell class="hidden xl:table-cell"><p>{{ $event->creator?->name ?? __('Unknown') }}</p><p class="text-xs text-slate-500">{{ $event->updated_at->diffForHumans() }}</p></flux:table.cell>
                                    <flux:table.cell align="end" class="pe-4">
                                        <x-events.admin-actions :event="$event" />
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>

                <div class="grid gap-4 md:hidden">
                    @foreach ($this->events as $event)
                        <article wire:key="event-card-{{ $event->id }}" class="rounded-xl border border-slate-200 p-4 dark:border-zinc-700">
                            <div class="flex gap-3">
                                <div class="flex h-16 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-church-maroon-950 text-church-gold-400">
                                    @if ($event->imageUrl())<img src="{{ $event->imageUrl() }}" alt="" class="h-full w-full object-cover">@else<flux:icon :name="$event->effectiveIcon()" class="size-6" />@endif
                                </div>
                                <div class="min-w-0 flex-1"><h3 class="font-semibold">{{ $event->title }}</h3><p class="text-xs text-slate-500">{{ $event->scheduleLabel() }}</p><p class="mt-1 text-xs">{{ $event->nextOccurrence()?->setTimezone($event->timezone)->format('j M Y · g:i A') ?? __('No future date') }}</p></div>
                                <x-events.admin-actions :event="$event" />
                            </div>
                        </article>
                    @endforeach
                    <flux:pagination :paginator="$this->events" />
                </div>
            @endif
        </div>
    </section>

    <flux:modal wire:model="showConfirmModal" class="max-w-lg">
        <form wire:submit="executeConfirmed" class="space-y-6">
            <div><flux:heading size="lg">{{ __('Confirm event action') }}</flux:heading><flux:text class="mt-2">{{ __('This state-changing action will be recorded in the activity log.') }}</flux:text></div>
            <div class="flex justify-end gap-3"><flux:button type="button" variant="ghost" wire:click="$set('showConfirmModal', false)">{{ __('Cancel') }}</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Confirm') }}</flux:button></div>
        </form>
    </flux:modal>
</div>
