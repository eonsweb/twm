@props([
    'sidebar' => false,
])

@php
    $churchName = data_get($publicSettings ?? [], 'church.official_name', config('app.name'));
@endphp

@if ($sidebar)
    <a
        {{ $attributes->class([
            'flex items-center gap-3 rounded-lg px-2 py-2 text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-church-gold-400',
        ]) }}
    >
        <x-app-logo-icon class="size-11 shrink-0 text-church-gold-400" />
        <span class="text-sm font-semibold leading-5">
            <span class="block max-w-36 text-balance">{{ $churchName }}</span>
            <span class="block text-xs font-normal text-white/70">{{ __('Administration') }}</span>
        </span>
    </a>
@else
    <a {{ $attributes->class(['flex items-center gap-2 font-semibold text-church-maroon-900 dark:text-white']) }}>
        <x-app-logo-icon class="size-9 text-church-gold-500" />
        <span>{{ $churchName }}</span>
    </a>
@endif
