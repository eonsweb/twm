<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ __('Website maintenance') }} — {{ data_get($church, 'official_name', config('app.name')) }}</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="font-body flex min-h-screen items-center justify-center bg-church-maroon-950 px-6 py-16 text-white">
        <main class="w-full max-w-2xl rounded-3xl border border-white/15 bg-white/10 p-8 text-center shadow-2xl backdrop-blur sm:p-12">
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-church-gold-400">
                {{ data_get($church, 'official_name', config('app.name')) }}
            </p>
            <h1 class="mt-4 text-3xl font-bold tracking-tight sm:text-4xl">{{ __('We will be back soon') }}</h1>
            <p class="mx-auto mt-4 max-w-xl text-base leading-7 text-white/80">
                {{ data_get($maintenance, 'message', __('The website is temporarily unavailable for maintenance.')) }}
            </p>

            @if (data_get($maintenance, 'contact_email'))
                <a class="mt-8 inline-flex rounded-xl bg-church-gold-500 px-5 py-3 font-semibold text-church-maroon-950 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white" href="mailto:{{ data_get($maintenance, 'contact_email') }}">
                    {{ __('Contact us') }}
                </a>
            @endif
        </main>
    </body>
</html>
