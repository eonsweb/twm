@props([
    'ministries',
    'heading' => null,
    'headingId' => 'ministries-heading',
])

@if ($ministries->isNotEmpty())
    <section data-ministries-section aria-labelledby="{{ $headingId }}" class="bg-stone-50 py-12 sm:py-14">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="relative flex items-center justify-center">
                <h2 id="{{ $headingId }}" class="font-heading text-center text-xl font-extrabold uppercase tracking-[0.14em] text-church-green-800 sm:text-2xl">
                    {{ $heading ?: __('Our Ministries') }}
                </h2>

                <div class="absolute right-0 hidden items-center gap-2 sm:flex">
                    <button type="button" data-ministries-prev class="grid size-10 place-items-center rounded-full border border-church-green-800/25 bg-white text-church-green-800 shadow-sm transition hover:border-church-green-800 hover:bg-church-green-800 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-church-green-800 disabled:pointer-events-none disabled:opacity-35 [&.swiper-button-disabled]:pointer-events-none [&.swiper-button-disabled]:opacity-35" aria-label="{{ __('Previous ministries') }}">
                        <flux:icon.arrow-left class="size-4" />
                    </button>
                    <button type="button" data-ministries-next class="grid size-10 place-items-center rounded-full border border-church-green-800/25 bg-white text-church-green-800 shadow-sm transition hover:border-church-green-800 hover:bg-church-green-800 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-church-green-800 disabled:pointer-events-none disabled:opacity-35 [&.swiper-button-disabled]:pointer-events-none [&.swiper-button-disabled]:opacity-35" aria-label="{{ __('Next ministries') }}">
                        <flux:icon.arrow-right class="size-4" />
                    </button>
                </div>
            </div>

            <div data-ministries-swiper class="swiper ministries-swiper mt-7 overflow-hidden pb-1" aria-label="{{ __('Ministries carousel') }}">
                <div class="swiper-wrapper">
                    @foreach ($ministries as $ministry)
                        <div class="swiper-slide h-auto" wire:key="homepage-ministry-{{ $ministry->id }}">
                            <article class="h-full py-1">
                                <a href="{{ route('public.ministries.show', $ministry) }}" wire:navigate class="group flex h-full flex-col rounded-lg border border-zinc-200 bg-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:border-church-gold-400 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-church-green-800 motion-reduce:transform-none motion-reduce:transition-none">
                                    <div class="relative">
                                        <div class="aspect-[4/3] overflow-hidden rounded-t-[calc(0.5rem-1px)] bg-stone-200 text-church-green-800">
                                            @if ($ministryImageUrl = $ministry->imageUrl())
                                                <img src="{{ $ministryImageUrl }}" alt="{{ __(':name ministry', ['name' => $ministry->name]) }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03] motion-reduce:transform-none motion-reduce:transition-none" loading="lazy" decoding="async">
                                            @else
                                                <div data-ministry-image-fallback class="grid h-full place-items-center bg-gradient-to-br from-stone-100 to-stone-200">
                                                    @if ($ministryLogoUrl = $ministry->logoUrl())
                                                        <img src="{{ $ministryLogoUrl }}" alt="" class="size-20 object-contain opacity-70" loading="lazy" decoding="async">
                                                    @else
                                                        <flux:icon.user-group class="size-16" aria-hidden="true" />
                                                    @endif
                                                </div>
                                            @endif
                                        </div>

                                        <span class="absolute bottom-0 left-1/2 z-10 grid size-12 -translate-x-1/2 translate-y-1/2 place-items-center rounded-full border-[3px] border-white bg-church-maroon-700 text-white shadow-md" aria-hidden="true">
                                            @if ($ministryLogoUrl = $ministry->logoUrl())
                                                <img src="{{ $ministryLogoUrl }}" alt="" class="size-7 object-contain brightness-0 invert" loading="lazy" decoding="async">
                                            @else
                                                <flux:icon.user-group class="size-6" />
                                            @endif
                                        </span>
                                    </div>

                                    <div class="flex flex-1 flex-col items-center px-3 pb-4 pt-8 text-center sm:px-4">
                                        <h3 class="font-heading text-sm font-extrabold uppercase leading-5 tracking-[0.08em] text-zinc-900">{{ $ministry->name }}</h3>
                                        <span class="mt-auto inline-flex items-center gap-1.5 pt-4 text-xs font-bold text-church-gold-600 transition group-hover:text-church-gold-500">
                                            {{ __('Learn More') }}
                                            <flux:icon.arrow-right class="size-3.5 transition-transform group-hover:translate-x-1 motion-reduce:transform-none motion-reduce:transition-none" aria-hidden="true" />
                                        </span>
                                    </div>
                                </a>
                            </article>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mt-10 flex h-1.5 w-full" aria-hidden="true">
            <div class="w-1/2 bg-church-maroon-900"></div>
            <div class="w-1/2 bg-church-gold-500"></div>
        </div>
    </section>
@endif
