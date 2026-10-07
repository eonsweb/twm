@props(['settings' => [], 'imageUrl' => null, 'section' => null, 'pageTitle' => null])
@php
    $hero = $section ? app(\App\Pages\HomepageHero::class)->resolve($section) : [];
    $content = $hero['settings'] ?? [];
    $slides = $hero['slides'] ?? collect();
    $video = $hero['video'] ?? null;
    $poster = ($hero['poster'] ?? null)?->publicImageUrl() ?: $section?->backgroundImage?->publicImageUrl() ?: $imageUrl;
    $social = $settings['social'] ?? [];
    $liveUrl = ($social['livestream_enabled'] ?? false) && filled($social['livestream_url'] ?? null) ? $social['livestream_url'] : route('public.sermons.index');
    $heading = $content['heading'] ?? data_get($settings, 'homepage.hero_title') ?: __('The Land of Overflow');
    $description = $content['description'] ?? data_get($settings, 'homepage.hero_description', __('A place to encounter God, discover purpose and live victoriously.'));
@endphp
<div data-cinematic-hero class="relative bg-[#090909] text-white">
    <section class="twm-hero relative isolate flex items-center overflow-hidden bg-[var(--twm-primary)]" aria-labelledby="homepage-hero-heading">
        @if($poster)
            <img data-hero-poster src="{{ $poster }}" alt="{{ data_get($content, 'background_alt', '') }}" fetchpriority="high" class="absolute inset-0 -z-20 size-full object-cover">
        @endif
        @if($video)
            <video data-background-video data-src="{{ $video->publicUrl() }}" data-type="{{ $video->mime_type }}" autoplay muted loop playsinline preload="metadata" @if($poster) poster="{{ $poster }}" @endif aria-hidden="true" tabindex="-1" class="absolute inset-0 -z-20 size-full object-cover"></video>
        @endif
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-black/80 via-black/45 to-black/20"></div>
        <div class="absolute inset-0 -z-10 bg-gradient-to-t from-[#090909] via-transparent to-black/20"></div>
        <div class="twm-container w-full pb-44 pt-40 sm:pb-48 lg:pt-48">
            <div class="max-w-4xl lg:pr-16">
                <p class="twm-eyebrow">{{ $content['eyebrow'] ?? __('Welcome to TWM') }}</p>
                <h1 id="homepage-hero-heading" class="mt-6 text-[clamp(2.5rem,8.5vw,8rem)] font-black uppercase leading-[0.92] tracking-[-0.055em] [overflow-wrap:anywhere] sm:text-[clamp(3.25rem,8.5vw,8rem)]">{{ $heading }}</h1>
                @if(filled(data_get($content, 'script_heading')))
                    <p class="mt-4 font-signature text-4xl text-[var(--twm-accent)]">{{ $content['script_heading'] }}</p>
                @endif
                @if(data_get($content, 'show_emblem', true) && ($hero['emblem'] ?? null))
                    <img src="{{ $hero['emblem']->publicImageUrl() }}" alt="{{ $hero['emblem']->alt_text }}" class="mt-5 h-20 w-auto object-contain">
                @endif
                @if(data_get($content, 'show_theme', true) && filled(data_get($content, 'theme')))
                    <p class="mt-5 max-w-xl text-xl text-[var(--twm-accent)]">{{ $content['theme'] }}</p>
                @endif
                @if(data_get($content, 'show_description', true) && filled($description))
                    <div class="mt-6 max-w-xl text-base leading-7 text-white/80 sm:text-lg">{!! app(\App\Blog\HtmlSanitizer::class)->sanitize($description) !!}</div>
                @endif
                <div class="mt-8 flex flex-wrap gap-3">
                    @if(data_get($content, 'show_primary_cta', true))
                        <a class="twm-button twm-button-accent" href="{{ $content['primary_url'] ?? $liveUrl }}">{{ $content['primary_label'] ?? __('Watch Now') }} <flux:icon.play class="size-4" /></a>
                    @endif
                    @if(data_get($content, 'show_secondary_cta', true))
                        <a class="twm-button border border-white/60 text-white hover:bg-white/10" href="{{ $content['secondary_url'] ?? '#visit' }}">{{ $content['secondary_label'] ?? __('Plan Your Visit') }} <flux:icon.arrow-down class="size-4" /></a>
                    @endif
                </div>
            </div>
        </div>
        <div class="absolute right-6 top-1/2 hidden -translate-y-1/2 flex-col gap-3 lg:flex" aria-label="{{ __('Follow TWM') }}">
            <x-public.home.social-links :social="$social" />
        </div>
        @if($video)
            <button data-video-toggle type="button" class="absolute bottom-32 right-6 z-10 rounded-full border border-white/40 bg-black/40 px-4 py-2 text-xs" aria-pressed="false">{{ __('Pause video') }}</button>
        @endif
    </section>
    @if($slides->isNotEmpty())
        <section data-message-section class="twm-container relative z-10 -mt-24 pb-8" aria-label="{{ __('Homepage highlights') }}">
            <div data-messages-swiper class="swiper twm-message-swiper">
                <div class="swiper-wrapper">
                    @foreach($slides as $slide)
                        @php($slideImage = $slide->media?->publicImageUrl() ?: $slide->videoPosterMedia?->publicImageUrl())
                        <article class="swiper-slide !h-auto" wire:key="hero-message-{{ $slide->id }}">
                            <div class="relative isolate flex h-full min-h-64 flex-col justify-end overflow-hidden border border-white/20 bg-white/10 p-7 backdrop-blur-xl">
                                @php($mobileImage = $slide->mobileMedia?->publicImageUrl())
                                @if($slideImage || $mobileImage)
                                    <picture class="absolute inset-0 -z-20 size-full">
                                        @if($mobileImage)<source media="(max-width: 767px)" srcset="{{ $mobileImage }}">@endif
                                        <img src="{{ $slideImage ?: $mobileImage }}" alt="{{ $slide->media?->alt_text }}" loading="lazy" class="size-full object-cover">
                                    </picture>
                                @endif
                                <div class="absolute inset-0 -z-10 bg-gradient-to-t from-black/95 via-black/60 to-black/20"></div>
                                <h2 class="max-w-sm text-2xl font-extrabold uppercase leading-tight tracking-tight">{{ $slide->title }}</h2>
                                @if($slide->description)<p class="mt-3 line-clamp-2 text-sm leading-6 text-white/75">{{ strip_tags($slide->description) }}</p>@endif
                                @if($slide->cta_url)
                                    <a href="{{ $slide->cta_url }}" class="mt-5 inline-flex items-center justify-between gap-4 text-xs font-bold uppercase tracking-wider text-[var(--twm-accent)]">{{ $slide->cta_text ?: __('Learn more') }}<flux:icon.arrow-up-right class="size-5" /></a>
                                @endif
                                @if($slide->secondary_cta_url)
                                    <a href="{{ $slide->secondary_cta_url }}" class="mt-3 text-xs font-semibold underline underline-offset-4">{{ $slide->secondary_cta_text ?: __('Find out more') }}</a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button data-messages-prev class="twm-arrow" aria-label="{{ __('Previous messages') }}"><flux:icon.arrow-left class="size-5" /></button>
                <button data-messages-next class="twm-arrow" aria-label="{{ __('Next messages') }}"><flux:icon.arrow-right class="size-5" /></button>
            </div>
        </section>
    @endif
</div>
