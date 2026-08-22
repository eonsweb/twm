@props(['schedules', 'settings' => []])

<section id="visit" aria-labelledby="service-times-heading" class="border-b border-zinc-200 bg-white">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
        <div class="text-center">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-church-gold-600">{{ __('Service Times') }}</p>
            <h2 id="service-times-heading" class="mt-2 font-heading text-2xl font-bold tracking-tight text-church-maroon-950 sm:text-3xl">{{ __('Join Us This Week') }}</h2>
        </div>

        <x-public.service-schedule-list :schedules="$schedules" />
    </div>
</section>
