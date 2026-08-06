@props(['settings' => [], 'imageUrl' => null])

@php
    $homepage = $settings['homepage'] ?? [];
    $church = $settings['church'] ?? [];
@endphp

<section class="relative isolate min-h-[32rem] overflow-hidden bg-zinc-950 text-white lg:min-h-[38rem]">
    @if ($imageUrl)
        <img src="{{ $imageUrl }}" alt="" class="absolute inset-0 -z-20 size-full object-cover object-center" fetchpriority="high">
    @else
        <div class="absolute inset-0 -z-20 bg-gradient-to-br from-church-green-950 via-zinc-950 to-church-maroon-950"></div>
        <div class="absolute -right-32 top-12 -z-10 size-96 rounded-full bg-church-gold-500/10 blur-3xl"></div>
    @endif
    <div class="absolute inset-0 -z-10 bg-gradient-to-r from-black via-black/75 to-black/25"></div>

    <div class="mx-auto grid min-h-[32rem] max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 lg:min-h-[38rem] lg:grid-cols-[1fr_18rem] lg:px-8">
        <div class="max-w-3xl text-center lg:text-left">
            <p class="font-signature text-4xl text-church-gold-400 sm:text-5xl">{{ $homepage['hero_eyebrow'] ?? __('Welcome to') }}</p>
            <h1 class="font-heading mt-2 text-5xl font-bold uppercase leading-[0.95] tracking-wide sm:text-6xl lg:text-7xl">
                <span class="text-red-600">{{ $homepage['hero_title'] ?? $church['official_name'] ?? config('app.name') }}</span>
            </h1>
            @if (filled($church['motto'] ?? null))
                <p class="font-heading mt-4 text-xl font-bold uppercase tracking-[0.15em] text-church-gold-400 sm:text-2xl">{{ $church['motto'] }}</p>
            @endif
            <p class="mx-auto mt-5 max-w-2xl text-base leading-7 text-zinc-200 lg:mx-0 lg:text-lg">{{ $homepage['hero_description'] ?? '' }}</p>
            <div class="mt-8 flex flex-wrap justify-center gap-3 lg:justify-start">
                <a href="#visit" class="rounded-md bg-red-700 px-6 py-3 text-sm font-bold uppercase transition hover:bg-red-600">{{ __('Plan Your Visit') }}</a>
                <a href="{{ route('public.sermons.index') }}" wire:navigate class="rounded-md border border-church-gold-400 px-6 py-3 text-sm font-bold uppercase transition hover:bg-church-gold-400 hover:text-zinc-950">{{ __('Watch Live') }}</a>
                <a href="{{ route('prayer-requests.create.public') }}" wire:navigate class="rounded-md bg-church-green-700 px-6 py-3 text-sm font-bold uppercase transition hover:bg-church-green-800">{{ __('Submit Prayer Request') }}</a>
            </div>
        </div>

        @if (filled($homepage['hero_scripture'] ?? null))
            <aside class="mx-auto hidden w-full max-w-72 rounded-lg border-2 border-church-gold-400 bg-black/80 p-7 text-center backdrop-blur lg:block" aria-label="{{ __('Featured scripture') }}">
                <p class="font-heading text-3xl uppercase leading-tight text-church-gold-300">{{ $homepage['hero_scripture'] }}</p>
                @if (filled($homepage['hero_scripture_reference'] ?? null))
                    <p class="mt-4 text-sm font-bold uppercase tracking-widest text-zinc-300">{{ $homepage['hero_scripture_reference'] }}</p>
                @endif
            </aside>
        @endif
    </div>
</section>
