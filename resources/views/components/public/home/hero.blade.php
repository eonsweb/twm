@props([
    'settings' => [],
    'imageUrl' => null,
    'section' => null,
    'pageTitle' => null,
])

@php
    $homepage = $settings['homepage'] ?? [];
    $church = $settings['church'] ?? [];
    $sectionSettings = $section?->settings ?? [];
    $backgroundMedia = $section?->backgroundImage;
    $backgroundUrl = $backgroundMedia?->publicImageUrl() ?? $imageUrl;
    $backgroundAlt = filled(data_get($sectionSettings, 'background_alt'))
        ? data_get($sectionSettings, 'background_alt')
        : ($backgroundMedia?->alt_text ?: $backgroundMedia?->name ?: __('Triumphant World Ministry church service'));
    $heading = $section?->heading ?: ($homepage['hero_title'] ?? $church['official_name'] ?? $pageTitle ?? config('app.name'));
    $eyebrow = $section?->subheading ?: data_get($sectionSettings, 'eyebrow', $homepage['hero_eyebrow'] ?? __('Welcome to'));
    $description = $section?->content ?: ($homepage['hero_description'] ?? '');
    $primaryLabel = data_get($sectionSettings, 'primary_label', __('Plan Your Visit'));
    $primaryUrl = data_get($sectionSettings, 'primary_url', '#visit');
    $secondaryLabel = data_get($sectionSettings, 'secondary_label', __('Watch Live'));
    $secondaryUrl = data_get($sectionSettings, 'secondary_url', route('public.sermons.index'));
@endphp

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
