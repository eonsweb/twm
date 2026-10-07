@props(['ministries', 'heading' => null, 'headingId' => 'ministries-heading', 'images' => []])
@if($ministries->isNotEmpty())
<section data-ministries-section class="overflow-hidden bg-[#f5f4f0] py-20 lg:py-28" aria-labelledby="{{ $headingId }}">
    <div class="twm-container">
        <div class="flex flex-wrap items-end justify-between gap-8">
            <div class="max-w-2xl"><p class="twm-eyebrow !text-[var(--twm-primary)]">{{ __('Ministries') }}</p><h2 id="{{ $headingId }}" class="twm-heading mt-4 text-zinc-950">{{ $heading ?: __('Ministries that move you') }}</h2><p class="mt-6 max-w-lg leading-7 text-zinc-600">{{ __("Discover your purpose and use your gifts to make a difference. There's a place for you at TWM.") }}</p><a href="{{ route('public.ministries.index') }}" wire:navigate class="mt-7 inline-flex items-center gap-4 text-xs font-bold uppercase tracking-widest text-[var(--twm-primary)]">{{ __('Explore all ministries') }} <flux:icon.arrow-up-right class="size-5" /></a></div>
            <div class="flex gap-2 text-[var(--twm-primary)]"><button type="button" data-ministries-prev class="twm-arrow !border-current" aria-label="{{ __('Previous ministries') }}"><flux:icon.arrow-left class="size-5" /></button><button type="button" data-ministries-next class="twm-arrow !border-current" aria-label="{{ __('Next ministries') }}"><flux:icon.arrow-right class="size-5" /></button></div>
        </div>
        <div data-ministries-swiper class="swiper mt-12" aria-label="{{ __('Ministries carousel') }}">
            <div class="swiper-wrapper items-end">
                @foreach($ministries as $ministry)
                    <article class="swiper-slide" wire:key="homepage-ministry-{{ $ministry->id }}">
                        <a href="{{ route('public.ministries.show', $ministry) }}" wire:navigate @class(['group relative isolate flex flex-col justify-end overflow-hidden bg-[var(--twm-primary)] p-7 text-white', 'min-h-96 lg:min-h-[30rem]' => $loop->odd, 'min-h-96 lg:min-h-[26rem]' => $loop->even])>
                            @if($image = ($images[$ministry->id] ?? null))<img src="{{ $image }}" alt="{{ __(':name ministry', ['name' => $ministry->name]) }}" loading="lazy" class="absolute inset-0 -z-20 size-full object-cover transition duration-700 group-hover:scale-105 motion-reduce:transform-none">@else<span data-ministry-image-fallback class="absolute inset-0 -z-20 grid place-items-center text-white/15"><flux:icon.user-group class="size-32" /></span>@endif
                            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-black/90 via-black/10 to-transparent"></div>
                            <h3 class="text-2xl font-extrabold uppercase leading-tight tracking-tight">{{ $ministry->name }}</h3><span class="mt-5 flex items-center justify-between text-xs font-bold uppercase tracking-widest text-[var(--twm-accent)]">{{ __('Learn More') }}<flux:icon.arrow-up-right class="size-5" /></span>
                        </a>
                    </article>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endif
