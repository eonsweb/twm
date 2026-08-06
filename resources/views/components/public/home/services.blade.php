@props(['schedules', 'settings' => []])

@php $contact = $settings['contact'] ?? []; @endphp

<section id="visit" aria-labelledby="service-times-heading" class="border-b border-zinc-200 bg-white">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <h2 id="service-times-heading" class="font-heading text-center text-2xl font-bold uppercase tracking-wider text-church-green-800">{{ __('Join Us This Week') }}</h2>
        <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @forelse ($schedules as $schedule)
                <article wire:key="service-{{ $schedule->id }}" class="flex items-start gap-4">
                    <span class="grid size-12 shrink-0 place-items-center rounded-full border-2 border-red-600 text-red-700"><flux:icon.calendar-days class="size-5" /></span>
                    <div>
                        <h3 class="text-sm font-extrabold uppercase">{{ $schedule->name }}</h3>
                        <p class="text-sm text-zinc-700">{{ $schedule->day_of_week }} {{ $schedule->formattedTime() }}</p>
                        @if ($schedule->location)<p class="mt-1 text-xs text-zinc-500">{{ $schedule->location }}</p>@endif
                    </div>
                </article>
            @empty
                <p class="text-sm text-zinc-600 sm:col-span-2 lg:col-span-3">{{ __('Service times will be announced soon. Contact us to plan your visit.') }}</p>
            @endforelse
            <div class="flex flex-col justify-center gap-2">
                @if (filled($contact['map_url'] ?? null))
                    <a href="{{ $contact['map_url'] }}" target="_blank" rel="noopener noreferrer" class="rounded-md bg-church-green-700 px-4 py-2.5 text-center text-xs font-bold uppercase text-white hover:bg-church-green-800">{{ __('Get Directions') }}</a>
                @endif
                @if (filled($contact['whatsapp_number'] ?? null))
                    <a href="https://wa.me/{{ preg_replace('/\D/', '', $contact['whatsapp_number']) }}" target="_blank" rel="noopener noreferrer" class="rounded-md border border-red-600 px-4 py-2.5 text-center text-xs font-bold uppercase text-red-700 hover:bg-red-50">{{ __('Chat on WhatsApp') }}</a>
                @endif
            </div>
        </div>
    </div>
</section>
