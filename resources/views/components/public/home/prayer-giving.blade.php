@props(['settings' => [], 'legacySettings' => [], 'prayerImageUrl' => null, 'givingImageUrl' => null])
@php
    $cards = [
        ['prayer', $prayerImageUrl, __('Need Prayer?'), __('We would love to stand with you in prayer.'), __('Submit Prayer Request'), route('prayer-requests.create.public')],
        ['giving', $givingImageUrl, __('Partner with the vision'), __('Your giving supports lives and spreads the Gospel.'), __('Give Now'), route('public.give')],
    ];
@endphp
<section data-prayer-giving aria-label="{{ __('Prayer requests and online giving') }}" class="grid bg-[#111111] text-white md:grid-cols-2">
    @foreach($cards as [$key, $image, $heading, $description, $button, $url])
        <article class="relative isolate flex min-h-[26rem] flex-col justify-center overflow-hidden border border-white/10 px-7 py-16 sm:px-12 lg:px-20">
            @if($image)<img src="{{ $image }}" alt="" loading="lazy" class="absolute inset-0 -z-20 size-full object-cover">@endif
            <div class="absolute inset-0 -z-10 bg-gradient-to-r from-black/85 to-black/40"></div>
            <p class="twm-eyebrow">{{ $key === 'prayer' ? __('We are here for you') : __('Make a difference') }}</p>
            <h2 class="mt-5 max-w-lg text-4xl font-black uppercase leading-tight tracking-tight">{{ data_get($settings, $key.'_heading') ?: data_get($legacySettings, 'homepage.'.$key.'_heading') ?: $heading }}</h2>
            <p class="mt-5 max-w-lg text-sm leading-7 text-white/75">{{ data_get($settings, $key.'_description') ?: data_get($legacySettings, 'homepage.'.$key.'_body') ?: $description }}</p>
            <a href="{{ $url }}" wire:navigate class="twm-button twm-button-accent mt-7 self-start">{{ data_get($settings, $key.'_button_text') ?: $button }}<flux:icon.arrow-up-right class="size-4" /></a>
        </article>
    @endforeach
</section>
