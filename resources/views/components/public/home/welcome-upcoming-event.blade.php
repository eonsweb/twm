@props([
    'leader' => null,
    'settings' => [],
    'sermon' => null,
    'events' => collect(),
    'section' => null,
    'imageUrl' => null,
    'aboutPage' => null,
    'sermonImageUrl' => null,
])

<div id="welcome-upcoming-event" class="w-full overflow-hidden">
    <x-public.home.welcome :leader="$leader" :settings="$settings" :section="$section" :image-url="$imageUrl" :about-page="$aboutPage" />

    <section aria-label="{{ __('Latest sermon and upcoming events') }}" class="bg-church-green-900 text-white">
        <div class="mx-auto grid max-w-7xl lg:grid-cols-2">
            <article class="flex flex-col border-b border-church-gold-400/60 px-6 py-8 sm:px-8 md:border-r md:border-b-0 xl:px-7">
                <h2 class="font-heading text-sm font-semibold uppercase tracking-[0.08em] text-white">{{ __('Latest Sermon') }}</h2>

                @if ($sermon)
                    <a href="{{ route('public.sermons.show', $sermon) }}" wire:navigate aria-label="{{ __('Watch :title', ['title' => $sermon->title]) }}" class="group relative mt-4 block aspect-video overflow-hidden rounded-sm bg-zinc-950 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-church-gold-300">
                        @if ($sermonImageUrl)
                            <img src="{{ $sermonImageUrl }}" alt="{{ __('Thumbnail for :title', ['title' => $sermon->title]) }}" class="size-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
                        @else
                            <div class="size-full bg-gradient-to-br from-church-maroon-950 to-zinc-950"></div>
                        @endif
                        <span class="absolute inset-0 grid place-items-center bg-black/15" aria-hidden="true">
                            <span class="grid h-12 w-[4.25rem] place-items-center rounded-md bg-red-700 shadow-xl transition group-hover:bg-red-600">
                                <flux:icon.play class="size-7 fill-current" />
                            </span>
                        </span>
                    </a>

                    <h3 class="mt-3 line-clamp-2 font-heading text-sm font-semibold leading-5 text-white">{{ $sermon->title }}</h3>
                    <p class="mt-1 text-xs text-green-100">{{ $sermon->speaker->full_name }}</p>
                    <a href="{{ route('public.sermons.show', $sermon) }}" wire:navigate class="mt-3 inline-flex w-fit rounded-sm bg-red-700 px-4 py-2 text-[0.7rem] font-bold uppercase tracking-wide text-white transition hover:bg-red-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                        {{ __('Watch Now') }}
                    </a>
                @else
                    <div class="mt-4 flex flex-1 items-center border border-white/15 bg-white/5 p-6 text-sm text-green-50">
                        {{ __('No sermons available yet.') }}
                    </div>
                @endif

                <a href="{{ route('public.sermons.index') }}" wire:navigate class="group mt-4 inline-flex w-fit items-center gap-2 text-[0.7rem] font-bold uppercase tracking-wide text-church-gold-300 transition hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-church-gold-300">
                    {{ __('View all sermons') }}
                    <flux:icon.arrow-right class="size-4 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
                </a>
            </article>

            <section class="px-6 py-8 sm:px-8 xl:px-7" aria-labelledby="upcoming-events-heading">
                <div class="flex items-center justify-between gap-4">
                    <h2 id="upcoming-events-heading" class="font-heading text-sm font-semibold uppercase tracking-[0.08em] text-white">{{ __('Upcoming Events') }}</h2>
                    <a href="{{ route('public.events.index') }}" wire:navigate class="group inline-flex shrink-0 items-center gap-1 text-[0.7rem] font-bold text-church-gold-300 transition hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-church-gold-300">
                        {{ __('View All Events') }}
                        <flux:icon.arrow-right class="size-3.5 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
                    </a>
                </div>

                <div class="mt-5 grid gap-4">
                    @forelse ($events as $event)
                        @php
                            $occurrence = $event->nextOccurrence() ?? $event->starts_at->setTimezone($event->timezone);
                            $eventStart = $event->starts_at->setTimezone($event->timezone);
                            $eventEnd = $event->ends_at?->setTimezone($event->timezone);
                            $eventTime = $event->is_all_day
                                ? __('All day')
                                : ($eventEnd && $eventStart->isSameDay($eventEnd)
                                    ? $eventStart->format('g:i A').' – '.$eventEnd->format('g:i A')
                                    : $eventStart->format('g:i A'));
                        @endphp
                        <a href="{{ route('public.events.show', $event) }}" wire:navigate wire:key="welcome-event-{{ $event->id }}" class="group grid min-w-0 grid-cols-[3.5rem_minmax(0,1fr)] items-center gap-4 rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-church-gold-300">
                            <time datetime="{{ $occurrence->toIso8601String() }}" class="grid min-h-14 place-content-center rounded-sm border border-church-gold-400 bg-church-green-950 px-2 py-1 text-center">
                                <span class="block text-[0.6rem] font-bold uppercase tracking-wider text-church-gold-300">{{ $occurrence->format('M') }}</span>
                                <span class="block font-heading text-xl font-bold leading-6 text-white">{{ $occurrence->format('d') }}</span>
                            </time>
                            <span class="min-w-0">
                                <span class="block line-clamp-1 font-heading text-xs font-bold uppercase leading-5 text-white transition group-hover:text-church-gold-200">{{ $event->title }}</span>
                                @if ($event->eventType)
                                    <span class="mt-0.5 block truncate text-[0.7rem] text-green-100">{{ $event->eventType->name }}</span>
                                @endif
                                <span class="mt-0.5 block text-[0.7rem] font-semibold text-church-gold-300">{{ $eventTime }}</span>
                            </span>
                        </a>
                    @empty
                        <div class="border border-white/15 bg-white/5 p-6 text-sm text-green-50">
                            {{ __('No upcoming events at the moment.') }}
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </section>
</div>
