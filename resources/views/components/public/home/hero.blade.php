@props(['settings' => [], 'imageUrl' => null, 'section' => null, 'pageTitle' => null])

@php
    $slides = $section
        ? \App\Models\HomepageHeroSlide::query()->where('page_id', $section->page_id)->visible()->with(['media', 'mobileMedia', 'videoPosterMedia', 'emblemMedia'])->orderBy('sort_order')->orderBy('id')->get()
        : collect();
@endphp

@if($slides->isEmpty())
    <x-public.home.hero-slide :settings="$settings" :image-url="$imageUrl" :section="$section" :page-title="$pageTitle" />
@else
    <div class="homepage-hero-swiper swiper" data-homepage-hero role="region" aria-roledescription="carousel" aria-label="{{ __('Homepage highlights') }}" style="--swiper-pagination-color: {{ data_get($settings, 'branding.accent_color', '#e8b949') }}">
        <div class="swiper-wrapper">
            @foreach($slides as $slide)
                <div class="swiper-slide" role="group" aria-roledescription="slide" aria-label="{{ __(':number of :total', ['number' => $loop->iteration, 'total' => $slides->count()]) }}" @if(!$loop->first) inert aria-hidden="true" @endif>
                    <x-public.home.hero-slide :settings="$settings" :section="$section" :slide="$slide" :first="$loop->first" :page-title="$pageTitle" />
                </div>
            @endforeach
        </div>
        @if($slides->count() > 1)
            <div class="swiper-pagination" data-hero-pagination></div>
        @endif
        <button type="button" data-hero-motion class="absolute bottom-5 right-4 z-30 rounded-md border border-white/70 bg-black/40 px-3 py-2 text-xs text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white" aria-pressed="false" hidden>{{ __('Pause motion') }}</button>
    </div>
@endif
