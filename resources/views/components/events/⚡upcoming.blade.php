<?php

use App\Models\Event;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public int $limit = 3;

    #[Computed]
    public function events()
    {
        return Event::query()
            ->published()
            ->upcoming()
            ->with('eventType:id,name')
            ->orderBy('starts_at')
            ->limit($this->limit)
            ->get();
    }
};
?>

<section aria-labelledby="upcoming-events-heading" class="py-12">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex items-end justify-between gap-4">
            <div><p class="text-sm font-bold uppercase tracking-widest text-church-maroon-700 dark:text-church-gold-400">{{ __('Gather with us') }}</p><h2 id="upcoming-events-heading" class="mt-2 text-3xl font-black tracking-tight">{{ __('Upcoming events') }}</h2></div>
            <a href="{{ route('public.events.index') }}" class="text-sm font-bold text-church-maroon-700 dark:text-church-gold-400" wire:navigate>{{ __('View all events') }}</a>
        </div>
        @if ($this->events->isEmpty())
            <div class="mt-8 rounded-2xl border border-dashed border-stone-300 p-10 text-center text-slate-500 dark:border-zinc-700">{{ __('No upcoming events have been published yet.') }}</div>
        @else
            <div class="mt-8 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->events as $event)<x-events.card :event="$event" wire:key="upcoming-event-{{ $event->id }}" />@endforeach
            </div>
        @endif
    </div>
</section>
