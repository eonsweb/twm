@props(['event'])

<article {{ $attributes->class('group overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900') }}>
    <a href="{{ route('public.events.show', $event) }}" class="block" wire:navigate>
        <div class="relative aspect-[16/9] overflow-hidden bg-church-maroon-950">
            @if ($event->imageUrl())
                <img src="{{ $event->imageUrl() }}" alt="{{ $event->title }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
            @else
                <div class="flex h-full items-center justify-center text-church-gold-400"><flux:icon.calendar-days class="size-14" /></div>
            @endif
            <div class="absolute inset-x-0 top-0 flex flex-wrap items-start justify-between gap-2 p-3">
                <flux:badge color="amber">{{ $event->eventType?->name ?? __('Event') }}</flux:badge>
                @if ($event->registration_required)<flux:badge color="blue">{{ __('Registration') }}</flux:badge>@endif
            </div>
        </div>
        <div class="space-y-3 p-5">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-church-maroon-700 dark:text-church-gold-400">{{ $event->starts_at->setTimezone($event->timezone)->format('D, M j') }}</p>
                <h3 class="mt-1 text-xl font-bold text-slate-950 group-hover:text-church-maroon-700 dark:text-white dark:group-hover:text-church-gold-400">{{ $event->title }}</h3>
            </div>
            <div class="space-y-1.5 text-sm text-slate-600 dark:text-zinc-300">
                <p class="flex gap-2"><flux:icon.clock class="mt-0.5 size-4 shrink-0" /><span>{{ $event->is_all_day ? __('All day') : $event->starts_at->setTimezone($event->timezone)->format('g:i A') }}</span></p>
                <p class="flex gap-2"><flux:icon.map-pin class="mt-0.5 size-4 shrink-0" /><span>{{ $event->locationLabel() }}</span></p>
            </div>
            @if ($event->short_description)<p class="line-clamp-3 text-sm leading-6 text-slate-600 dark:text-zinc-400">{{ $event->short_description }}</p>@endif
            <span class="inline-flex items-center gap-2 text-sm font-bold text-church-maroon-800 dark:text-church-gold-400">{{ __('View event') }}<flux:icon.arrow-right class="size-4 transition group-hover:translate-x-1" /></span>
        </div>
    </a>
</article>
