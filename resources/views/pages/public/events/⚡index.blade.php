<?php

use App\Models\Event;
use App\Models\EventType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.public'), Title('Events')] class extends Component
{
    use WithPagination;

    #[Url] public string $search = '';
    #[Url(as: 'type')] public string $eventType = '';
    #[Url] public string $period = 'upcoming';

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function events(): LengthAwarePaginator
    {
        return Event::query()
            ->active()
            ->published()
            ->with(['eventType:id,name,slug,icon,color', 'featuredImage:id,disk,path,media_type,visibility,status'])
            ->when($this->period === 'past', fn (Builder $query): Builder => $query->past())
            ->when($this->period !== 'past', fn (Builder $query): Builder => $query->upcoming())
            ->when($this->eventType !== '', fn (Builder $query): Builder => $query->ofType($this->eventType))
            ->when($this->search !== '', function (Builder $query): void {
                $search = '%'.trim($this->search).'%';
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', $search)
                        ->orWhere('short_description', 'like', $search)
                        ->orWhere('description', 'like', $search)
                        ->orWhere('venue_name', 'like', $search)
                        ->orWhere('city', 'like', $search);
                });
            })
            ->orderBy('sort_order')
            ->orderBy('starts_at', $this->period === 'past' ? 'desc' : 'asc')
            ->paginate(9);
    }

    #[Computed]
    public function eventTypes()
    {
        return EventType::query()
            ->where('is_active', true)
            ->whereHas('events', fn (Builder $query): Builder => $query->published())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }
};
?>

<div>
    <section class="bg-church-maroon-950 text-white">
        <div class="mx-auto max-w-7xl px-4 pb-16 pt-28 sm:px-6 sm:pb-20 sm:pt-32 lg:px-8 lg:pt-36">
            <p class="text-sm font-bold uppercase tracking-[0.2em] text-church-gold-400">{{ __('Life together') }}</p>
            <h1 class="mt-3 text-4xl font-black tracking-tight sm:text-5xl">{{ __('Church events') }}</h1>
            <p class="mt-5 max-w-2xl text-lg leading-8 text-white/75">{{ __('Discover services, conferences, prayer gatherings, outreach, and programmes where we worship, grow, and serve together.') }}</p>
        </div>
    </section>

    <livewire:events.featured />

    <section class="mx-auto max-w-7xl bg-white px-4 py-12 text-zinc-950 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_15rem_auto]">
                <flux:input wire:model.live.debounce.350ms="search" icon="magnifying-glass" :label="__('Search events')" :placeholder="__('Search by event or location')" />
                <flux:select wire:model.live="eventType" :label="__('Event type')"><flux:select.option value="">{{ __('All types') }}</flux:select.option>@foreach ($this->eventTypes as $type)<flux:select.option :value="$type->slug">{{ $type->name }}</flux:select.option>@endforeach</flux:select>
                <flux:radio.group wire:model.live="period" :label="__('Period')" variant="segmented"><flux:radio value="upcoming" :label="__('Upcoming')" /><flux:radio value="past" :label="__('Past')" /></flux:radio.group>
            </div>
        </div>

        <div class="relative mt-8">
            <div wire:loading.flex class="absolute inset-0 z-10 items-start justify-center bg-stone-50/75 pt-20 backdrop-blur-sm dark:bg-zinc-950/75"><flux:icon.arrow-path class="size-6 animate-spin" /></div>
            @if ($this->events->isEmpty())
                <div class="rounded-2xl border border-dashed border-stone-300 p-12 text-center dark:border-zinc-700">
                    <flux:icon.calendar-days class="mx-auto size-10 text-slate-400" />
                    <h2 class="mt-4 text-xl font-bold">{{ __('No events found') }}</h2>
                    <p class="mt-2 text-slate-500">{{ __('Try a different search, event type, or date period.') }}</p>
                </div>
            @else
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($this->events as $event)<x-events.card :event="$event" wire:key="public-event-{{ $event->id }}" />@endforeach
                </div>
                <div class="mt-8">
                <flux:pagination :paginator="$this->events" />
                </div>
            @endif
        </div>
    </section>
</div>
