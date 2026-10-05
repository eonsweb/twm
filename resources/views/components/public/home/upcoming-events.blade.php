@props(['events', 'section' => null, 'viewAllUrl', 'eventType' => null])
@php($options = $section?->settings ?? [])
<section data-upcoming-events class="bg-white py-20 lg:py-28" aria-labelledby="events-heading">
    <div class="twm-container">
        <div class="flex flex-wrap items-end justify-between gap-6">
            <div><p class="twm-eyebrow !text-[var(--twm-primary)]">{{ __('Upcoming Events') }}</p><h2 id="events-heading" class="twm-heading mt-4 max-w-2xl text-zinc-950">{{ $section?->heading ?: __('What?s happening at TWM') }}</h2></div>
            @if(data_get($options, 'show_read_more', data_get($options, 'show_view_all', true)))<a href="{{ $viewAllUrl }}" wire:navigate class="twm-button border border-[var(--twm-primary)] text-[var(--twm-primary)]">{{ data_get($options, 'view_all_label', __('View All Events')) }} <flux:icon.arrow-up-right class="size-4" /></a>@endif
        </div>
        @if($section?->content)<div class="mt-5 max-w-2xl leading-7 text-zinc-600">{!! app(\App\Blog\HtmlSanitizer::class)->sanitize($section->content) !!}</div>@endif
        <div class="mt-12 grid gap-8 md:grid-cols-2 lg:grid-cols-3">
            @forelse($events as $event)
                @php($occurrence = ($event->nextOccurrence() ?? $event->starts_at)->copy()->setTimezone($event->timezone))
                <article data-upcoming-event-item wire:key="homepage-event-{{ $event->id }}">
                    <a href="{{ route('public.events.show', $event) }}" wire:navigate class="group block">
                        <div class="relative grid aspect-[4/3] place-items-center overflow-hidden bg-stone-100 text-[var(--twm-primary)]">
                            @if($eventImage = $event->imageUrl())
                                <img data-event-image src="{{ $eventImage }}" alt="{{ $event->title }}" loading="lazy" class="size-full object-cover transition duration-700 group-hover:scale-105 motion-reduce:transform-none">
                            @else
                                <span data-event-image-fallback><flux:icon :name="$event->effectiveIcon()" class="size-16" /></span>
                            @endif
                            <time datetime="{{ $occurrence->toIso8601String() }}" class="absolute bottom-0 left-0 bg-[var(--twm-primary)] px-5 py-4 text-center text-white"><span class="block text-xs font-bold uppercase tracking-widest">{{ $occurrence->format('M') }}</span><span class="text-3xl font-black">{{ $occurrence->format('d') }}</span></time>
                        </div>
                        <h3 class="mt-6 text-2xl font-extrabold uppercase tracking-tight text-zinc-950 group-hover:text-[var(--twm-primary)]">{{ $event->title }}</h3>
                        <p class="mt-3 flex items-center gap-2 text-sm text-zinc-600"><flux:icon.clock class="size-4" />{{ $event->is_all_day ? __('All Day') : $occurrence->format('g:i A') }}</p>
                        @if($event->location)<p class="mt-2 flex items-center gap-2 text-sm text-zinc-600"><flux:icon.map-pin class="size-4" />{{ $event->location }}</p>@endif
                        @if($event->eventType)<p class="mt-3 text-xs font-bold uppercase tracking-wider text-[var(--twm-primary)]">{{ $event->eventType->name }}</p>@endif
                    </a>
                </article>
            @empty
                <p class="border border-dashed border-stone-300 p-10 text-zinc-500 md:col-span-2 lg:col-span-3">{{ __('No upcoming events at this time.') }}</p>
            @endforelse
        </div>
    </div>
</section>
