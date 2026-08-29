@props([
    'settings' => [],
    'imageUrl' => null,
    'section' => null,
    'pageTitle' => null,
])

@php
    $homepage = $settings['homepage'] ?? [];
    $church = $settings['church'] ?? [];
    $resolvedHero = $section ? app(\App\Pages\HomepageHero::class)->resolve($section) : null;
    $sectionSettings = $resolvedHero['settings'] ?? [];
    $emblemMedia = $resolvedHero['emblem'] ?? null;
    $backgroundMedia = $section?->backgroundImage;
    $backgroundUrl = $backgroundMedia?->publicImageUrl() ?? $imageUrl;
    $backgroundAlt = filled(data_get($sectionSettings, 'background_alt'))
        ? data_get($sectionSettings, 'background_alt')
        : ($backgroundMedia?->alt_text ?: $backgroundMedia?->name ?: __('Triumphant World Ministry church service'));
    $heading = $sectionSettings['heading'] ?? ($homepage['hero_title'] ?? $church['official_name'] ?? $pageTitle ?? config('app.name'));
    $eyebrow = $sectionSettings['eyebrow'] ?? ($homepage['hero_eyebrow'] ?? __('Welcome to'));
    $description = $sectionSettings['description'] ?? ($homepage['hero_description'] ?? '');
    $primaryLabel = data_get($sectionSettings, 'primary_label', __('Plan Your Visit'));
    $primaryUrl = data_get($sectionSettings, 'primary_url', '#visit');
    $secondaryLabel = data_get($sectionSettings, 'secondary_label', __('Watch Live'));
    $secondaryUrl = data_get($sectionSettings, 'secondary_url', route('public.sermons.index'));
    $primaryColor = data_get($settings, 'branding.primary_color', '#681c2d');
    $accentColor = data_get($settings, 'branding.accent_color', '#e8b949');
    $isAnniversary = $section && data_get($sectionSettings, 'variant') === 'anniversary';
@endphp

@if($isAnniversary)
    <section
        x-data="heroParallax"
        class="relative isolate min-h-[44rem] overflow-hidden text-white lg:min-h-[min(54rem,100svh)]"
        style="--hero-primary: {{ $primaryColor }}; --hero-accent: {{ $accentColor }}; background-color: var(--hero-primary);"
        aria-labelledby="homepage-hero-heading"
    >
        <div class="absolute inset-0 z-0 overflow-hidden">
            @if($backgroundUrl)
                <img
                    x-ref="image"
                    src="{{ $backgroundUrl }}"
                    alt="{{ $backgroundAlt }}"
                    @if($backgroundMedia?->width) width="{{ $backgroundMedia->width }}" @endif
                    @if($backgroundMedia?->height) height="{{ $backgroundMedia->height }}" @endif
                    loading="eager"
                    fetchpriority="high"
                    class="hero-parallax-image absolute inset-x-0 -top-[5%] h-[110%] w-full object-cover object-[68%_center] will-change-transform"
                >
            @endif

            <div class="absolute inset-0 bg-[var(--hero-primary)] opacity-45" aria-hidden="true"></div>
            <div class="absolute inset-0 bg-[linear-gradient(90deg,var(--hero-primary)_0%,var(--hero-primary)_38%,transparent_100%)] lg:bg-[linear-gradient(90deg,var(--hero-primary)_0%,var(--hero-primary)_34%,color-mix(in_srgb,var(--hero-primary)_78%,transparent)_56%,transparent_82%)]" aria-hidden="true"></div>
            <div class="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-t from-black/55 to-transparent" aria-hidden="true"></div>
        </div>

        <div class="relative z-20 mx-auto flex min-h-[44rem] max-w-7xl items-center px-4 pb-20 pt-28 sm:px-6 sm:pt-32 lg:min-h-[min(54rem,100svh)] lg:px-8 lg:pb-20 lg:pt-28">
            <div class="w-full max-w-3xl text-center lg:w-[62%] lg:text-left">
                @if(data_get($sectionSettings, 'show_emblem', true))
                    <div class="mb-4 flex justify-center lg:justify-start">
                        @if($emblemMedia?->publicImageUrl())
                            <img
                                src="{{ $emblemMedia->publicImageUrl() }}"
                                alt="{{ $emblemMedia->alt_text ?: $emblemMedia->name }}"
                                @if($emblemMedia->width) width="{{ $emblemMedia->width }}" @endif
                                @if($emblemMedia->height) height="{{ $emblemMedia->height }}" @endif
                                class="max-h-28 w-auto max-w-64 object-contain sm:max-h-32"
                            >
                        @else
                            <div class="flex items-center gap-3 text-[var(--hero-accent)]" aria-label="{{ __(':number years anniversary', ['number' => data_get($sectionSettings, 'anniversary_number', '20')]) }}">
                                <span class="h-px w-10 bg-[var(--hero-accent)] sm:w-14"></span>
                                <span class="font-heading text-7xl font-bold leading-none sm:text-8xl">{{ data_get($sectionSettings, 'anniversary_number', '20') }}</span>
                                <span class="font-heading text-sm font-bold uppercase tracking-[0.25em]">{{ data_get($sectionSettings, 'anniversary_unit', 'YEARS') }}</span>
                                <span class="h-px w-10 bg-[var(--hero-accent)] sm:w-14"></span>
                            </div>
                        @endif
                    </div>
                @endif

                @if(filled($eyebrow))
                    <p class="text-sm font-bold uppercase tracking-[0.3em] text-[var(--hero-accent)] sm:text-base">{{ $eyebrow }}</p>
                @endif

                <h1 id="homepage-hero-heading" class="font-heading mt-3 text-5xl font-bold leading-[0.92] tracking-tight text-white sm:text-6xl lg:whitespace-nowrap lg:text-6xl xl:text-7xl">
                    {{ $heading }}
                </h1>

                @if(filled(data_get($sectionSettings, 'script_heading')))
                    <p class="font-signature -mt-1 text-6xl leading-none text-[var(--hero-accent)] sm:text-7xl lg:text-8xl">
                        {{ data_get($sectionSettings, 'script_heading') }}
                    </p>
                @endif

                @if(data_get($sectionSettings, 'show_theme', true) && filled(data_get($sectionSettings, 'theme')))
                    <div class="mx-auto mt-5 max-w-2xl lg:mx-0">
                        <div class="mb-4 flex items-center gap-3" aria-hidden="true">
                            <span class="h-px flex-1 bg-[var(--hero-accent)]"></span>
                            <span class="size-2 rotate-45 bg-[var(--hero-accent)]"></span>
                            <span class="h-px flex-1 bg-[var(--hero-accent)]"></span>
                        </div>
                        <p class="font-heading text-2xl leading-tight sm:text-3xl lg:text-4xl">
                            <span class="text-[var(--hero-accent)]">“</span>{{ data_get($sectionSettings, 'theme') }}<span class="text-[var(--hero-accent)]">”</span>
                        </p>
                    </div>
                @endif

                @if(data_get($sectionSettings, 'show_description', true) && filled($description))
                    <div class="prose prose-invert mx-auto mt-5 max-w-2xl text-base leading-7 text-white/90 lg:mx-0 lg:text-lg">
                        {!! app(\App\Blog\HtmlSanitizer::class)->sanitize($description) !!}
                    </div>
                @endif

                <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row sm:flex-wrap lg:justify-start">
                    @if(data_get($sectionSettings, 'show_primary_cta', true) && filled($primaryLabel) && filled($primaryUrl))
                        <a href="{{ $primaryUrl }}" class="inline-flex min-h-12 items-center justify-center rounded-md bg-[var(--hero-accent)] px-6 py-3 text-sm font-bold uppercase text-[var(--hero-primary)] transition hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--hero-accent)]">
                            {{ $primaryLabel }}
                            <svg class="ml-2 size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                        </a>
                    @endif

                    @if(data_get($sectionSettings, 'show_secondary_cta', true) && filled($secondaryLabel) && filled($secondaryUrl))
                        <a href="{{ $secondaryUrl }}" class="inline-flex min-h-12 items-center justify-center rounded-md border border-[var(--hero-accent)] bg-black/15 px-6 py-3 text-sm font-bold uppercase text-[var(--hero-accent)] transition hover:bg-black/30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--hero-accent)]">
                            {{ $secondaryLabel }}
                            <svg class="ml-2 size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M16 3v4M8 3v4M3 11h18" /></svg>
                        </a>
                    @endif
                </div>
            </div>
        </div>

        @if(data_get($sectionSettings, 'show_scroll_indicator', true))
            <span class="absolute bottom-4 left-1/2 z-20 hidden size-11 -translate-x-1/2 place-items-center rounded-full border border-[var(--hero-accent)] text-[var(--hero-accent)] sm:grid" aria-hidden="true">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6" /></svg>
            </span>
        @endif
    </section>
@else
    <section
        x-data="heroParallax"
        class="relative isolate min-h-screen overflow-hidden bg-[rgb(36,11,54)] text-white"
        aria-labelledby="homepage-hero-heading"
    >
        <div class="absolute inset-0 z-0 overflow-hidden">
            @if ($backgroundUrl)
                <img
                    x-ref="image"
                    src="{{ $backgroundUrl }}"
                    alt="{{ $backgroundAlt }}"
                    @if($backgroundMedia?->width) width="{{ $backgroundMedia->width }}" @endif
                    @if($backgroundMedia?->height) height="{{ $backgroundMedia->height }}" @endif
                    loading="eager"
                    fetchpriority="high"
                    class="hero-parallax-image absolute inset-x-0 -top-[5%] h-[110%] w-full object-cover object-center will-change-transform"
                >
            @endif

            <div class="absolute inset-0 bg-[linear-gradient(to_right,rgb(195,20,50),rgb(36,11,54))] opacity-95" aria-hidden="true"></div>
        </div>

        <div class="relative z-20 mx-auto grid min-h-screen max-w-7xl items-center gap-12 px-4 pb-16 pt-28 sm:px-6 sm:pt-32 lg:grid-cols-[1fr_18rem] lg:px-8">
            <div class="max-w-3xl text-center lg:text-left">
                @if (filled($eyebrow))
                    <p class="font-signature text-4xl text-church-gold-400 sm:text-5xl">{{ $eyebrow }}</p>
                @endif

                <h1 id="homepage-hero-heading" class="font-heading mt-2 text-5xl font-bold uppercase leading-[0.95] tracking-wide sm:text-6xl lg:text-7xl">
                    <span class="text-white">{{ $heading }}</span>
                </h1>

                @if (! $section && filled($church['motto'] ?? null))
                    <p class="font-heading mt-4 text-xl font-bold uppercase tracking-[0.15em] text-church-gold-400 sm:text-2xl">{{ $church['motto'] }}</p>
                @endif

                @if (filled($description))
                    <div class="prose prose-invert mx-auto mt-5 max-w-2xl text-base leading-7 text-zinc-100 lg:mx-0 lg:text-lg">
                        {!! app(\App\Blog\HtmlSanitizer::class)->sanitize($description) !!}
                    </div>
                @endif

                <div class="mt-8 flex flex-wrap justify-center gap-3 lg:justify-start">
                    @if (filled($primaryLabel) && filled($primaryUrl))
                        <a href="{{ $primaryUrl }}" class="rounded-md bg-white px-6 py-3 text-sm font-bold uppercase text-[rgb(195,20,50)] transition hover:bg-church-gold-300 hover:text-[rgb(36,11,54)]">
                            {{ $primaryLabel }}
                        </a>
                    @endif

                    @if (filled($secondaryLabel) && filled($secondaryUrl))
                        <a href="{{ $secondaryUrl }}" class="rounded-md border border-white/80 px-6 py-3 text-sm font-bold uppercase transition hover:bg-white hover:text-[rgb(36,11,54)]">
                            {{ $secondaryLabel }}
                        </a>
                    @endif

                    @unless ($section)
                        <a href="{{ route('prayer-requests.create.public') }}" wire:navigate class="rounded-md bg-church-green-700 px-6 py-3 text-sm font-bold uppercase transition hover:bg-church-green-800">
                            {{ __('Submit Prayer Request') }}
                        </a>
                    @endunless
                </div>
            </div>

            @if (! $section && filled($homepage['hero_scripture'] ?? null))
                <aside class="mx-auto hidden w-full max-w-72 rounded-lg border-2 border-church-gold-400 bg-black/40 p-7 text-center backdrop-blur lg:block" aria-label="{{ __('Featured scripture') }}">
                    <p class="font-heading text-3xl uppercase leading-tight text-church-gold-300">{{ $homepage['hero_scripture'] }}</p>
                    @if (filled($homepage['hero_scripture_reference'] ?? null))
                        <p class="mt-4 text-sm font-bold uppercase tracking-widest text-zinc-200">{{ $homepage['hero_scripture_reference'] }}</p>
                    @endif
                </aside>
            @endif
        </div>
    </section>
@endif
