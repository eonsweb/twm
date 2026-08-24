@props(['settings' => []])

@php
    $branding = $settings['branding'] ?? [];
    $social = $settings['social'] ?? [];
    $church = $settings['church'] ?? [];
    $logoPath = $branding['primary_logo'] ?? null;
    $logoUrl = filled($logoPath) ? Storage::disk('public')->url($logoPath) : null;
    $liveUrl = ($social['livestream_enabled'] ?? false) && filled($social['livestream_url'] ?? null)
        ? $social['livestream_url']
        : route('public.sermons.index');
    $navigation = [
        ['label' => __('Home'), 'url' => route('home'), 'active' => request()->routeIs('home')],
        ['label' => __('About'), 'url' => route('home').'#about', 'active' => false],
        ['label' => __('Visit Us'), 'url' => route('home').'#visit', 'active' => false],
        ['label' => __('Sermons'), 'url' => route('public.sermons.index'), 'active' => request()->routeIs('public.sermons.*')],
        ['label' => __('Events'), 'url' => route('public.events.index'), 'active' => request()->routeIs('public.events.*')],
        ['label' => __('Ministries'), 'url' => route('public.ministries.index'), 'active' => request()->routeIs('public.ministries.*')],
        ['label' => __('Books'), 'url' => route('public.books.index'), 'active' => request()->routeIs('public.books.*')],
        ['label' => __('Give'), 'url' => route('public.give'), 'active' => request()->routeIs('public.give')],
        ['label' => __('Contact'), 'url' => route('public.contact'), 'active' => request()->routeIs('public.contact')],
    ];
    foreach (($publicNavigationPages ?? []) as $navigationPage) {
        $navigation[] = ['label' => $navigationPage->navigation_label ?: $navigationPage->title, 'url' => route('public.pages.show', $navigationPage), 'active' => request()->routeIs('public.pages.show') && request()->route('page')?->is($navigationPage)];
    }
@endphp

<header
    x-data="navbar"
    x-cloak
    class="fixed top-0 left-0 z-50 w-full border-b border-white/10 text-white backdrop-blur-[4px] transition-all duration-300"
    x-bind:class="isScrolled ? 'bg-gray-900/95 shadow-lg' : 'bg-transparent'"
>
    <div class="mx-auto flex min-h-20 max-w-7xl items-center justify-between gap-5 px-4 sm:px-6 lg:px-8">
        <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3" wire:navigate aria-label="{{ __('Go to homepage') }}">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $church['official_name'] ?? config('app.name') }}" class="h-14 w-auto max-w-64 object-contain" fetchpriority="high">
            @else
                <span class="grid size-12 shrink-0 place-items-center rounded-full border border-church-gold-400 bg-church-maroon-900 font-bold text-church-gold-300">{{ $church['short_name'] ?? 'TWM' }}</span>
                <span class="min-w-0">
                    <span class="font-heading block truncate text-lg font-bold uppercase tracking-wide text-red-500">{{ $church['official_name'] ?? config('app.name') }}</span>
                    @if (filled($church['motto'] ?? null))
                        <span class="block truncate text-[0.65rem] font-bold uppercase tracking-[0.2em] text-church-gold-400">{{ $church['motto'] }}</span>
                    @endif
                </span>
            @endif
        </a>

        <nav aria-label="{{ __('Main navigation') }}" class="font-heading hidden items-center gap-5 text-xs font-semibold lg:flex">
            @foreach ($navigation as $item)
                <a href="{{ $item['url'] }}" @class(['border-b-2 py-7 transition hover:text-church-gold-300', 'border-church-gold-400 text-church-gold-300' => $item['active'], 'border-transparent text-white' => ! $item['active']]) wire:navigate>{{ $item['label'] }}</a>
            @endforeach
        </nav>

        <div class="flex items-center gap-2">
            <a href="{{ $liveUrl }}" @if (str_starts_with($liveUrl, 'http')) target="_blank" rel="noopener noreferrer" @else wire:navigate @endif class="hidden rounded-md bg-red-700 px-4 py-2.5 text-xs font-bold uppercase shadow-lg transition hover:bg-red-600 sm:inline-flex">
                {{ __('Watch Live') }}
            </a>
            <button type="button" class="grid size-11 place-items-center rounded-md border border-white/20 lg:hidden" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="public-mobile-menu" aria-label="{{ __('Toggle navigation') }}">
                <flux:icon.bars-3 x-show="! open" class="size-5" />
                <flux:icon.x-mark x-cloak x-show="open" class="size-5" />
            </button>
        </div>
    </div>

    <nav id="public-mobile-menu" x-cloak x-show="open" x-on:keydown.escape.window="open = false" x-transition aria-label="{{ __('Mobile navigation') }}" class="font-heading absolute inset-x-0 top-full border-t border-white/10 bg-zinc-950 px-4 py-4 shadow-2xl lg:hidden">
        <div class="mx-auto grid max-w-7xl gap-1">
            @foreach ($navigation as $item)
                <a href="{{ $item['url'] }}" x-on:click="open = false" @class(['rounded-md px-4 py-3 text-sm font-semibold hover:bg-white/10', 'text-church-gold-300' => $item['active']]) wire:navigate>{{ $item['label'] }}</a>
            @endforeach
            <a href="{{ $liveUrl }}" @if (str_starts_with($liveUrl, 'http')) target="_blank" rel="noopener noreferrer" @else wire:navigate @endif class="mt-2 rounded-md bg-red-700 px-4 py-3 text-center text-sm font-bold uppercase">{{ __('Watch Live') }}</a>
        </div>
    </nav>
</header>
