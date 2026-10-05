@props(['schedules', 'settings' => [], 'section' => null])

<section id="visit" aria-labelledby="service-times-heading" class="bg-[#f7f6f3] py-12 lg:py-20">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
        <div class="text-center">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-church-gold-600">{{ __('Plan Your Visit') }}</p>
            <h2 id="service-times-heading" class="twm-heading mt-4 text-zinc-950">{{ $section?->heading ?: __('Join Us at TWM') }}</h2>
        </div>

        <x-public.service-schedule-list :schedules="$schedules" />
        @if(data_get($settings, 'contact.physical_address'))
            <p class="mt-10 flex items-center justify-center gap-3 text-center text-sm"><flux:icon.map-pin class="size-5 shrink-0 text-[var(--twm-primary)]" />{{ data_get($settings, 'contact.physical_address') }}</p>
        @endif
        @if(data_get($settings, 'contact.map_url'))
            <div class="mt-6 text-center"><a href="{{ data_get($settings, 'contact.map_url') }}" target="_blank" rel="noopener noreferrer" class="twm-button bg-[var(--twm-primary)] text-white">{{ __('Get Directions') }} <flux:icon.arrow-up-right class="size-4" /></a></div>
        @endif
    </div>
</section>
