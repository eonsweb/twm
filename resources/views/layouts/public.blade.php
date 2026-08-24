<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @php
            $isHomepage = request()->routeIs('home');
            $title = $isHomepage ? data_get($publicSettings, 'general.website_name', config('app.name')) : ($title ?? null);
            $titleSuffix = ! $isHomepage;
        @endphp
        @include('partials.head')
        @stack('meta')
    </head>
    <body class="font-body min-h-screen bg-white text-zinc-950 antialiased">
        <a href="#public-main" class="fixed start-4 top-4 z-[60] -translate-y-24 rounded-md bg-white px-4 py-2 text-sm font-bold text-zinc-950 shadow-xl transition focus:translate-y-0">
            {{ __('Skip to main content') }}
        </a>

        <x-public.site-header :settings="$publicSettings" />

        <main id="public-main">{{ $slot }}</main>

        <x-public.site-footer :settings="$publicSettings" />

        @fluxScripts
    </body>
</html>
