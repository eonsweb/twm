@props([
    'events',
    'section',
    'viewAllUrl',
    'eventType' => null,
])

@php
    $items = collect($events)->values();
    $featuredEvent = $items->first();
    $secondaryEvents = $items->slice(1, 3);
    $settings = $section->settings ?? [];
    $featuredOccurrence = $featuredEvent?->nextOccurrence() ?? $featuredEvent?->starts_at;
@endphp

<section data-upcoming-events class="bg-church-green-900 py-16 text-white lg:py-24" aria-labelledby="section-{{ $section->id }}-heading">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-4">
            <span class="h-px w-10 bg-church-gold-400" aria-hidden="true"></span>
            <h2 id="section-{{ $section->id }}-heading" class="font-heading text-2xl font-bold uppercase tracking-[0.16em] text-white sm:text-3xl">
                {{ $section->heading ?: __('Upcoming Events') }}
            </h2>
        </div>

        @if($section->content)
            <div class="prose prose-invert mt-5 max-w-3xl text-white/75">{!! app(\App\Blog\HtmlSanitizer::class)->sanitize($section->content) !!}</div>
        @endif

        @if($featuredEvent)
            <div @class(['mt-10 grid overflow-hidden border border-white/10 bg-church-green-950/45', 'lg:grid-cols-2' => $secondaryEvents->isNotEmpty()])>
                <article class="min-w-0">
                    <a href="{{ route('public.events.show', $featuredEvent) }}" wire:navigate class="group block focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-church-gold-300">
                        <div class="flex h-72 items-center justify-center overflow-hidden bg-church-green-950 text-church-gold-400 sm:h-96 lg:h-[28rem]">
                            @if($featuredEventImageUrl = $featuredEvent->imageUrl())
                                <img data-event-image src="{{ $featuredEventImageUrl }}" alt="{{ __(':title event', ['title' => $featuredEvent->title]) }}" class="h-full w-full object-cover object-top transition duration-500 group-hover:scale-[1.02]">
                            @else
                                <span data-event-image-fallback><flux:icon :name="$featuredEvent->effectiveIcon()" class="size-20" /></span>
                            @endif
                        </div>
                    </a>

                    <div class="p-6 sm:p-8 lg:p-10">
                        @if($featuredOccurrence)
                            <p class="flex items-center gap-2 text-sm font-semibold text-church-gold-300">
                                <flux:icon.calendar-days class="size-5 shrink-0" />
                                <time datetime="{{ $featuredOccurrence->toIso8601String() }}">{{ $featuredOccurrence->copy()->setTimezone($featuredEvent->timezone)->format('F j, Y') }}</time>
                            </p>
                        @endif
                        <h3 class="mt-4 font-heading text-2xl font-bold uppercase tracking-tight text-white sm:text-3xl">{{ $featuredEvent->title }}</h3>
                        @if($featuredEvent->short_description || $featuredEvent->description)
                            <p class="mt-4 line-clamp-3 max-w-xl text-base leading-7 text-white/70">{{ str(strip_tags($featuredEvent->short_description ?: $featuredEvent->description))->limit(190) }}</p>
                        @endif
                        <a href="{{ route('public.events.show', $featuredEvent) }}" wire:navigate class="group mt-6 inline-flex items-center gap-2 font-heading text-sm font-bold text-church-gold-300 transition hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-church-gold-300">
                            {{ __('Read More') }}
                            <flux:icon.arrow-right class="size-4 transition group-hover:translate-x-1" />
                        </a>
                    </div>
                </article>

                @if($secondaryEvents->isNotEmpty())
                    <div class="flex min-w-0 flex-col justify-center border-t border-white/10 px-6 sm:px-8 lg:border-t-0 lg:border-l lg:px-10">
                        @foreach($secondaryEvents as $event)
                            @php
                                $occurrence = $event->nextOccurrence() ?? $event->starts_at;
                                $localOccurrence = $occurrence->copy()->setTimezone($event->timezone);
                                $localStart = $event->starts_at->copy()->setTimezone($event->timezone);
                                $localEnd = $event->ends_at?->copy()->setTimezone($event->timezone);
                                $timeLabel = $event->is_all_day
                                    ? __('All Day')
                                    : $localStart->format('g:i A').($localEnd ? ' – '.$localEnd->format('g:i A') : '');
                            @endphp
                            <a data-upcoming-event-item href="{{ route('public.events.show', $event) }}" wire:navigate wire:key="homepage-upcoming-event-{{ $event->id }}" class="group grid min-w-0 grid-cols-[4.5rem_minmax(0,1fr)] items-center gap-4 border-b border-white/10 py-7 transition last:border-b-0 hover:bg-white/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-church-gold-300 sm:grid-cols-[5rem_minmax(0,1fr)] sm:gap-6">
                                <time datetime="{{ $occurrence->toIso8601String() }}" class="grid min-h-20 place-content-center rounded-lg border border-church-gold-400/60 bg-church-green-950 px-2 text-center transition group-hover:border-church-gold-300">
                                    <span class="font-heading text-xs font-bold uppercase tracking-[0.14em] text-church-gold-300">{{ $localOccurrence->format('M') }}</span>
                                    <span class="mt-1 font-heading text-2xl font-bold leading-none text-white">{{ $localOccurrence->format('d') }}</span>
                                </time>
                                <span class="min-w-0">
                                    <span class="block font-heading text-lg font-semibold uppercase leading-snug text-white transition group-hover:text-church-gold-300 sm:text-xl">{{ $event->title }}</span>
                                    @if($event->eventType)
                                        <span class="mt-1 block text-sm text-white/60">{{ $event->eventType->name }}</span>
                                    @endif
                                    <span class="mt-2 block text-sm font-semibold text-church-gold-300">{{ $timeLabel }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @else
            <div class="mt-10 border border-dashed border-white/20 px-6 py-12 text-center text-white/70">{{ __('No upcoming events at this time.') }}</div>
        @endif

        @if(data_get($settings, 'show_read_more', data_get($settings, 'show_view_all', true)))
            <div class="mt-10 text-center">
                <a href="{{ $viewAllUrl }}" wire:navigate class="inline-flex items-center gap-2 rounded-md border border-church-gold-400 px-6 py-3 font-heading text-sm font-bold uppercase tracking-wider text-church-gold-300 transition hover:bg-church-gold-400 hover:text-church-green-950 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-church-gold-300">
                    {{ data_get($settings, 'view_all_label', $eventType ? __('See all :type events', ['type' => $eventType->name]) : __('View More Events')) }}
                    <flux:icon.arrow-right class="size-4" />
                </a>
            </div>
        @endif
    </div>
</section>
