@props(['schedules'])

<div class="mt-6 grid grid-cols-1 gap-x-8 gap-y-6 md:grid-cols-2 lg:grid-cols-[repeat(auto-fit,minmax(13rem,1fr))] lg:justify-items-center">
    @forelse ($schedules as $schedule)
        <article wire:key="service-schedule-{{ $schedule->id }}" class="flex items-center gap-4">
            <span aria-hidden="true" class="grid size-12 shrink-0 place-items-center rounded-full border-2 border-church-maroon-800 text-church-maroon-800 sm:size-14">
                <flux:icon.calendar-days aria-hidden="true" class="size-5 sm:size-6" />
            </span>

            <div class="flex min-w-0 flex-col">
                <span class="text-xs font-bold uppercase tracking-[0.14em] text-church-maroon-800">{{ $schedule->day_of_week }}</span>
                <h3 class="font-heading text-base font-semibold leading-snug text-church-maroon-950">{{ $schedule->name }}</h3>
                <p class="text-sm text-zinc-600">
                    {{ $schedule->formattedTime() }}
                    @if ($schedule->end_time)
                        <span aria-hidden="true">&ndash;</span>
                        <span class="sr-only">{{ __('to') }}</span>
                        {{ \Illuminate\Support\Carbon::parse((string) $schedule->end_time)->format('g:i A') }}
                    @endif
                </p>
            </div>
        </article>
    @empty
        <p class="text-center text-sm text-zinc-600 md:col-span-2">{{ __('Service times will be announced soon.') }}</p>
    @endforelse
</div>
