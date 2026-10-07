@props(['sermon' => null, 'section' => null, 'imageUrl' => null])
<section data-featured-sermon class="bg-[var(--twm-primary)] py-20 text-white lg:py-28" aria-labelledby="sermon-heading">
    <div class="twm-container grid items-center gap-10 lg:grid-cols-[1.15fr_1fr] lg:gap-16">
        @if($sermon)
            <a href="{{ route('public.sermons.show', $sermon) }}" wire:navigate class="group relative grid aspect-video place-items-center overflow-hidden bg-black/40" aria-label="{{ __('Watch :title', ['title' => $sermon->title]) }}">
                @if($imageUrl)<img src="{{ $imageUrl }}" alt="" loading="lazy" class="absolute inset-0 size-full object-cover transition duration-700 group-hover:scale-105 motion-reduce:transform-none">@endif
                <span class="absolute inset-0 bg-black/20"></span>
                <span class="relative grid size-20 place-items-center rounded-full border border-white/70 bg-white/10 text-white backdrop-blur"><flux:icon.play class="size-8 fill-current" /></span>
            </a>
        @else
            <div class="grid aspect-video place-items-center border border-white/20 bg-black/20 p-8 text-center text-white/70">{{ __('No sermons are available yet.') }}</div>
        @endif
        <div>
            <p class="twm-eyebrow">{{ $section?->subheading ?: __('Latest Sermon') }}</p>
            <h2 id="sermon-heading" class="twm-heading mt-4">{{ $section?->heading ?: __('Listen to our messages') }}</h2>
            @if($sermon)
                <h3 class="mt-6 text-2xl font-semibold">{{ $sermon->title }}</h3>
                <p class="mt-3 text-sm text-white/70">{{ $sermon->speaker?->full_name }} &middot; <time datetime="{{ $sermon->sermon_date->toDateString() }}">{{ $sermon->sermon_date->format('F j, Y') }}</time></p>
                @if($sermon->scripture_reference)<p class="mt-3 text-sm text-[var(--twm-accent)]">{{ $sermon->scripture_reference }}</p>@endif
                @if($sermon->summary)<p class="mt-5 line-clamp-3 leading-7 text-white/70">{{ $sermon->summary }}</p>@endif
                <a href="{{ route('public.sermons.show', $sermon) }}" wire:navigate class="twm-button twm-button-accent mt-7">{{ __('Watch Message') }} <flux:icon.arrow-up-right class="size-4" /></a>
            @endif
            <a href="{{ route('public.sermons.index') }}" wire:navigate class="mt-7 block text-xs font-bold uppercase tracking-widest underline underline-offset-8">{{ __('Explore all sermons') }}</a>
        </div>
    </div>
</section>
