@props(['sermon' => null, 'events'])

<section aria-label="{{ __('Latest sermon and upcoming events') }}" class="bg-church-green-900 text-white">
    <div class="mx-auto grid max-w-7xl lg:grid-cols-2">
        <article class="border-b border-church-gold-400/50 p-7 sm:p-10 lg:border-b-0 lg:border-r">
            <h2 class="font-heading text-xl uppercase text-church-gold-100">{{ __('Latest Sermon') }}</h2>
            @if ($sermon)
                <a href="{{ route('public.sermons.show', $sermon) }}" wire:navigate class="group relative mt-5 block aspect-video overflow-hidden rounded-md bg-zinc-950">
                    @if ($sermon->thumbnailUrl())
                        <img src="{{ $sermon->thumbnailUrl() }}" alt="" class="size-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
                    @else
                        <div class="size-full bg-gradient-to-br from-church-maroon-950 to-zinc-950"></div>
                    @endif
                    <span class="absolute inset-0 grid place-items-center bg-black/15"><span class="grid size-16 place-items-center rounded-md bg-red-700 shadow-xl"><flux:icon.play class="size-8 fill-current" /></span></span>
                </a>
                <h3 class="mt-4 text-base font-semibold">{{ $sermon->title }}</h3>
                <p class="mt-1 text-sm text-green-100">{{ $sermon->speaker->full_name }} &middot; {{ $sermon->sermon_date->format('M j, Y') }}</p>
                <a href="{{ route('public.sermons.show', $sermon) }}" wire:navigate class="mt-4 inline-flex rounded bg-red-700 px-5 py-2.5 text-xs font-bold uppercase hover:bg-red-600">{{ __('Watch Now') }}</a>
            @else
                <div class="mt-5 rounded-lg border border-white/15 bg-white/5 p-8 text-sm text-green-50">{{ __('The next sermon will appear here once it is published.') }}</div>
            @endif
            <a href="{{ route('public.sermons.index') }}" wire:navigate class="mt-5 inline-flex text-xs font-bold uppercase tracking-wide text-church-gold-300 hover:text-white">{{ __('View all sermons') }} &rarr;</a>
        </article>

        <section class="p-7 sm:p-10" aria-labelledby="upcoming-events-heading">
            <div class="flex items-center justify-between gap-4">
                <h2 id="upcoming-events-heading" class="font-heading text-xl uppercase text-church-gold-100">{{ __('Upcoming Events') }}</h2>
                <a href="{{ route('public.events.index') }}" wire:navigate class="text-xs font-bold text-church-gold-300 hover:text-white">{{ __('View All Events') }} &rarr;</a>
            </div>
            <div class="mt-5 space-y-5">
                @forelse ($events as $event)
                    <a href="{{ route('public.events.show', $event) }}" wire:navigate wire:key="event-{{ $event->id }}" class="group grid grid-cols-[4rem_1fr] gap-4">
                        <time datetime="{{ $event->starts_at->toDateString() }}" class="rounded-md border border-church-gold-400 p-2 text-center">
                            <span class="block text-[0.65rem] font-bold uppercase text-church-gold-300">{{ $event->starts_at->format('M') }}</span>
                            <span class="font-heading block text-2xl font-bold">{{ $event->starts_at->format('d') }}</span>
                        </time>
                        <span>
                            <span class="block font-bold uppercase group-hover:text-church-gold-200">{{ $event->title }}</span>
                            @if ($event->eventType)<span class="mt-1 block text-xs text-green-200">{{ $event->eventType->name }}</span>@endif
                            <span class="mt-1 block text-sm font-semibold text-church-gold-300">{{ $event->is_all_day ? __('All day') : $event->starts_at->format('g:i A') }}</span>
                        </span>
                    </a>
                @empty
                    <p class="rounded-lg border border-white/15 bg-white/5 p-8 text-sm text-green-50">{{ __('New events are coming soon. Check back for updates.') }}</p>
                @endforelse
            </div>
        </section>
    </div>
</section>
