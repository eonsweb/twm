@props(['section', 'data' => []])
@php
    $settings = $section->settings ?? [];
    $isServiceTimes = $section->section_type->value === 'service-times';
    $isFeaturedSermons = $section->section_type->value === 'featured-sermons';
    $isWelcome = $section->section_type->value === 'welcome';
    $isWelcomeUpcomingEvent = $section->section_type->value === 'welcome-upcoming-event';
@endphp
@if($isWelcome)
    <x-public.home.welcome
        :leader="$data['leader'] ?? null"
        :settings="$data['settings'] ?? []"
        :section="$section"
    />
@elseif($isWelcomeUpcomingEvent)
    <x-public.home.welcome-upcoming-event
        :leader="$data['leader'] ?? null"
        :settings="$data['settings'] ?? []"
        :sermon="$data['sermon'] ?? null"
        :events="$data['events'] ?? collect()"
        :section="$section"
    />
@else
<section @class(['py-8 sm:py-10' => $isServiceTimes, 'bg-stone-50/70 py-16 lg:py-24' => $isFeaturedSermons, 'py-12 sm:py-16' => ! $isServiceTimes && ! $isFeaturedSermons]) aria-labelledby="section-{{ $section->id }}-heading">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div @class(['text-center' => $isServiceTimes])>
            @if($section->subheading || $isServiceTimes || $isFeaturedSermons)<p @class(['font-bold uppercase', 'text-xs tracking-[0.18em] text-church-gold-600' => $isServiceTimes, 'text-sm tracking-[0.16em] text-church-gold-700' => ! $isServiceTimes])>{{ $section->subheading ?: ($isFeaturedSermons ? __('Latest Sermon') : __('Service Times')) }}</p>@endif
            @if($section->heading || $isServiceTimes || $isFeaturedSermons)<h2 id="section-{{ $section->id }}-heading" class="mt-2 text-3xl font-bold tracking-tight text-church-maroon-950 sm:text-4xl">{{ $section->heading ?: ($isFeaturedSermons ? __('Featured Sermon') : __('Join Us This Week')) }}</h2>@endif
            @if($section->content)<div @class(['prose mt-5 max-w-none text-slate-700', 'mx-auto' => $isServiceTimes])>{!! app(\App\Blog\HtmlSanitizer::class)->sanitize($section->content) !!}</div>@endif
        </div>

        @switch($section->section_type->value)
            @case('hero')
                <div class="mt-8 flex flex-wrap gap-3">@if(data_get($settings,'primary_label') && data_get($settings,'primary_url'))<a href="{{ data_get($settings,'primary_url') }}" class="rounded-lg bg-church-maroon-900 px-5 py-3 font-semibold text-white">{{ data_get($settings,'primary_label') }}</a>@endif @if(data_get($settings,'secondary_label') && data_get($settings,'secondary_url'))<a href="{{ data_get($settings,'secondary_url') }}" class="rounded-lg border border-church-maroon-900 px-5 py-3 font-semibold text-church-maroon-900">{{ data_get($settings,'secondary_label') }}</a>@endif</div>
                @break
            @case('upcoming-events')
                @if(data_get($settings, 'display_style', 'cards') === 'weekly-events')
                    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        @forelse(($data['items'] ?? []) as $event)
                            <a href="{{ route('public.events.show', $event) }}" wire:navigate wire:key="weekly-section-event-{{ $event->id }}" class="group rounded-2xl border border-stone-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                                @if(data_get($settings, 'show_icon', true))<span class="flex size-11 items-center justify-center rounded-xl bg-church-maroon-950 text-church-gold-400"><flux:icon :name="$event->effectiveIcon()" class="size-6" /></span>@endif
                                <h3 class="mt-4 font-bold text-church-maroon-950 group-hover:text-church-maroon-700">{{ $event->title }}</h3>
                                @if(data_get($settings, 'show_day', true))<p class="mt-2 text-sm font-semibold text-slate-600">{{ collect($event->recurrence_days ?? [])->map(fn($day) => str($day)->title())->join(', ', ' & ') ?: $event->nextOccurrence()?->format('l, j M') }}</p>@endif
                                @if(data_get($settings, 'show_time', true))<p class="mt-1 text-sm text-slate-500">{{ $event->is_all_day ? __('All day') : $event->starts_at->setTimezone($event->timezone)->format('g:i A') }}</p>@endif
                            </a>
                        @empty
                            <p class="text-slate-500">{{ __('No events are available for this section yet.') }}</p>
                        @endforelse
                    </div>
                @else
                    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">@forelse(($data['items'] ?? []) as $event)<x-events.card :event="$event" wire:key="section-event-{{ $event->id }}" />@empty<p class="text-slate-500">{{ __('No events are available for this section yet.') }}</p>@endforelse</div>
                @endif
                @if(data_get($settings, 'show_read_more', data_get($settings, 'show_view_all', true)))<a href="{{ $data['viewAllUrl'] ?? route('public.events.index') }}" class="mt-7 inline-flex items-center gap-2 font-bold text-church-maroon-800" wire:navigate>{{ data_get($settings, 'view_all_label', data_get($data, 'eventType') ? __('See all :type events', ['type' => data_get($data, 'eventType.name')]) : __('View all events')) }}<flux:icon.arrow-right class="size-4" /></a>@endif
                @break
            @case('featured-sermons')
                @php($sermon = collect($data['items'] ?? [])->first())
                @if($sermon)
                    <div data-featured-sermon class="mt-8 grid overflow-hidden rounded-3xl border border-stone-200 bg-white shadow-xl lg:grid-cols-[minmax(0,1.15fr)_minmax(20rem,0.85fr)]">
                        <div class="min-w-0 bg-slate-950">
                            <x-sermons.media-player :sermon="$sermon" class="h-full rounded-none shadow-none" />
                        </div>
                        <div class="flex flex-col justify-center p-6 sm:p-8 lg:p-10 xl:p-12">
                            <time datetime="{{ $sermon->sermon_date->toDateString() }}" class="text-xs font-bold uppercase tracking-[0.16em] text-church-gold-600">{{ $sermon->sermon_date->format('F j, Y') }}</time>
                            <h3 class="mt-3 text-2xl font-bold tracking-tight text-church-maroon-950 sm:text-3xl lg:text-4xl">{{ $sermon->title }}</h3>
                            @if($sermon->relationLoaded('speaker') && $sermon->speaker)
                                <p class="mt-4 text-sm font-semibold text-church-maroon-700 sm:text-base">{{ __('Speaker: :speaker', ['speaker' => $sermon->speaker->full_name]) }}</p>
                            @endif
                            @if($sermon->summary)
                                <p class="mt-5 line-clamp-4 text-base leading-7 text-slate-600 sm:text-lg">{{ $sermon->summary }}</p>
                            @endif
                            <div class="mt-7">
                                <a href="{{ route('public.sermons.show', $sermon) }}" class="inline-flex items-center gap-2 rounded-xl bg-church-maroon-900 px-5 py-3 font-bold text-white shadow-sm transition hover:bg-church-maroon-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-church-maroon-900" wire:navigate>
                                    <flux:icon.play class="size-5" />
                                    {{ __('Watch Now') }}
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="mt-8 text-center">
                        <a href="{{ route('public.sermons.index') }}" class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.18em] text-church-gold-700 transition hover:text-church-maroon-800" wire:navigate>
                            {{ __('View All Sermons') }}
                            <flux:icon.arrow-right class="size-4" />
                        </a>
                    </div>
                @else
                    <div class="mt-8 rounded-2xl border border-dashed border-stone-300 bg-white px-6 py-10 text-center text-slate-500">{{ __('No sermons are available yet.') }}</div>
                @endif
                @break
            @case('latest-posts') @case('ministries-grid') @case('leadership-grid') @case('books-grid')
                <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">@forelse(($data['items'] ?? []) as $item)<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="text-lg font-bold text-church-maroon-950">{{ $item->title ?? $item->name ?? $item->full_name }}</h3><p class="mt-2 line-clamp-3 text-sm text-slate-600">{{ $item->excerpt ?? $item->short_description ?? $item->summary ?? $item->description }}</p></article>@empty<p class="text-slate-500">{{ __('Nothing to show yet.') }}</p>@endforelse</div>
                @break
            @case('service-times')
                <x-public.service-schedule-list :schedules="$data['items'] ?? []" />
                @break
            @case('church-locations') @case('contact-details')
                <div class="mt-8 grid gap-4 sm:grid-cols-2"><div class="rounded-2xl border border-slate-200 p-6"><h3 class="font-bold text-church-maroon-950">{{ data_get($data,'settings.church.official_name',config('app.name')) }}</h3><p class="mt-2 text-slate-600">{{ data_get($data,'settings.contact.physical_address') }}</p></div><div class="rounded-2xl border border-slate-200 p-6"><p><a class="font-semibold text-church-maroon-800" href="mailto:{{ data_get($data,'settings.contact.primary_email') }}">{{ data_get($data,'settings.contact.primary_email') }}</a></p><p class="mt-2">{{ data_get($data,'settings.contact.primary_phone') }}</p></div></div>
                @break
            @case('livestream')
                @if(data_get($data,'settings.social.livestream_enabled') && data_get($data,'settings.social.livestream_url'))<div class="mt-8"><a href="{{ data_get($data,'settings.social.livestream_url') }}" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-lg bg-red-700 px-5 py-3 font-bold text-white">{{ __('Watch livestream') }}</a></div>@endif
                @break
            @case('donation-callout')<div class="mt-8"><a href="{{ route('public.give') }}" class="inline-flex rounded-lg bg-church-gold-500 px-5 py-3 font-bold text-church-maroon-950">{{ data_get($settings,'button_label',__('Give now')) }}</a></div>@break
            @case('prayer-request-callout')<div class="mt-8"><a href="{{ route('prayer-requests.create.public') }}" class="inline-flex rounded-lg bg-church-maroon-900 px-5 py-3 font-bold text-white">{{ data_get($settings,'button_label',__('Submit a prayer request')) }}</a></div>@break
        @endswitch
    </div>
</section>
@endif
