@props(['section' => null, 'images' => []])
@php
    $cards = [
        ['salvation', __('Salvation'), __('Begin a relationship with Jesus Christ.'), 'heart', route('public.contact')],
        ['prayer', __('Prayer Request'), __('Let us stand with you in prayer.'), 'hand-raised', route('prayer-requests.create.public')],
        ['join', __('Join TWM'), __('Become part of our church family.'), 'user-group', route('public.contact')],
        ['give', __('Give'), __('Partner with what God is doing through TWM.'), 'gift', route('public.give')],
    ];
@endphp
<section data-next-steps class="relative overflow-hidden bg-[#111111] py-20 text-white lg:py-28" aria-labelledby="next-steps-heading">
    <div class="twm-container relative">
        <p class="twm-eyebrow">{{ $section?->subheading ?: __('Take your next step') }}</p>
        <h2 id="next-steps-heading" class="twm-heading mt-4 max-w-3xl">{{ $section?->heading ?: __('Find your place. Live your purpose.') }}</h2>
        @if($section?->content)<div class="mt-6 max-w-2xl text-white/70">{!! app(\App\Blog\HtmlSanitizer::class)->sanitize($section->content) !!}</div>@endif
        <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($cards as [$key, $title, $description, $icon, $url])
                @php($image = collect($images)->get((int) data_get($section?->settings, $key.'_media_id'))?->publicImageUrl())
                <a href="{{ $url }}" wire:navigate class="group relative isolate flex min-h-96 flex-col justify-end overflow-hidden border border-white/15 bg-[var(--twm-primary)] p-7" wire:key="next-step-{{ $key }}">
                    @if($image)<img src="{{ $image }}" alt="" loading="lazy" class="absolute inset-0 -z-20 size-full object-cover transition duration-700 group-hover:scale-105 motion-reduce:transform-none">@endif
                    <div class="absolute inset-0 -z-10 bg-gradient-to-t from-black/90 via-black/30 to-transparent"></div>
                    <flux:icon :name="$icon" class="mb-auto size-8 text-[var(--twm-accent)]" />
                    <h3 class="mt-14 text-2xl font-extrabold uppercase tracking-tight">{{ $title }}</h3>
                    <p class="mt-3 text-sm leading-6 text-white/75">{{ $description }}</p>
                    <span class="mt-6 grid size-10 place-items-center rounded-full border border-white/50"><flux:icon.arrow-up-right class="size-4" /></span>
                </a>
            @endforeach
        </div>
    </div>
</section>
