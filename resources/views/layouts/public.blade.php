<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        @stack('meta')
    </head>
    <body class="min-h-screen bg-stone-50 text-slate-950 antialiased dark:bg-zinc-950 dark:text-white">
        <a href="#public-main" class="fixed start-4 top-4 z-50 -translate-y-24 rounded-lg bg-white px-4 py-2 text-sm font-semibold shadow-lg transition focus:translate-y-0 dark:bg-zinc-800">
            {{ __('Skip to main content') }}
        </a>

        <header class="border-b border-stone-200 bg-white/95 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/95">
            <div class="mx-auto flex min-h-20 max-w-7xl items-center justify-between gap-6 px-4 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="font-bold tracking-tight text-church-maroon-950 dark:text-white" wire:navigate>
                    {{ config('app.name') }}
                </a>
                <nav aria-label="{{ __('Main navigation') }}" class="flex items-center gap-4 text-sm font-semibold">
                    <a href="{{ route('home') }}" class="hover:text-church-maroon-700 dark:hover:text-church-gold-400" wire:navigate>{{ __('Home') }}</a>
                    <a href="{{ route('public.sermons.index') }}" class="hover:text-church-maroon-700 dark:hover:text-church-gold-400" wire:navigate>{{ __('Sermons') }}</a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="rounded-lg bg-church-maroon-900 px-3 py-2 text-white hover:bg-church-maroon-800" wire:navigate>{{ __('Dashboard') }}</a>
                    @endauth
                </nav>
            </div>
        </header>

        <main id="public-main">{{ $slot }}</main>

        <footer class="mt-16 border-t border-stone-200 bg-white py-10 text-center text-sm text-slate-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-400">
            &copy; {{ now()->year }} {{ config('app.name') }}
        </footer>

        @fluxScripts
    </body>
</html>
