@props([
    'settings' => [],
    'legacySettings' => [],
    'prayerImageUrl' => null,
    'givingImageUrl' => null,
])

@php
    $legacyHomepage = $legacySettings['homepage'] ?? [];
    $prayerHeading = data_get($settings, 'prayer_heading') ?: data_get($legacyHomepage, 'prayer_heading') ?: __('Need Prayer?');
    $prayerDescription = data_get($settings, 'prayer_description') ?: data_get($legacyHomepage, 'prayer_body') ?: __('We would love to stand with you in prayer.');
    $prayerButtonText = data_get($settings, 'prayer_button_text') ?: __('Submit Prayer Request');
    $givingHeading = data_get($settings, 'giving_heading') ?: data_get($legacyHomepage, 'giving_heading') ?: __('Partner With the Work of God');
    $givingDescription = data_get($settings, 'giving_description') ?: data_get($legacyHomepage, 'giving_body') ?: __("Your giving supports lives, spreads the Gospel, and advances God's Kingdom.");
    $givingButtonText = data_get($settings, 'giving_button_text') ?: __('Give Online');
    $prayerImageUrl ??= asset('images/home/prayer-hands.webp');
    $givingImageUrl ??= asset('images/home/giving-hands.webp');
@endphp

<section data-prayer-giving aria-label="{{ __('Prayer requests and online giving') }}">
    <div class="grid grid-cols-1 md:grid-cols-2">
        <article class="relative isolate flex min-h-64 items-center overflow-hidden bg-church-maroon-950 text-white sm:min-h-68 md:min-h-60">
            <img
                src="{{ $prayerImageUrl }}"
                alt=""
                aria-hidden="true"
                class="absolute inset-0 -z-20 size-full object-cover object-left"
                loading="lazy"
            >
            <div class="absolute inset-0 -z-10 bg-gradient-to-r from-church-maroon-950/10 via-church-maroon-900/75 to-church-maroon-950/95"></div>

            <div class="w-full py-9 pr-6 pl-[34%] sm:py-10 sm:pr-8 md:pl-[32%] lg:pr-10 lg:pl-[34%] xl:pr-12">
                <h2 class="font-heading text-xl font-bold uppercase tracking-[0.04em] text-white sm:text-2xl">
                    {{ $prayerHeading }}
                </h2>
                <p class="mt-1.5 max-w-sm font-body text-sm leading-6 text-white/90">
                    {{ $prayerDescription }}
                </p>
                <a
                    href="{{ route('prayer-requests.create.public') }}"
                    wire:navigate
                    class="mt-5 inline-flex min-h-11 items-center justify-center rounded-md bg-church-gold-400 px-5 py-2.5 font-heading text-xs font-bold uppercase text-church-maroon-950 shadow-sm transition hover:bg-church-gold-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-church-gold-300"
                >
                    {{ $prayerButtonText }}
                </a>
            </div>
        </article>

        <article class="relative isolate flex min-h-64 items-center overflow-hidden bg-church-gold-600 text-white sm:min-h-68 md:min-h-60">
            <img
                src="{{ $givingImageUrl }}"
                alt=""
                aria-hidden="true"
                class="absolute inset-0 -z-20 size-full object-cover object-left"
                loading="lazy"
            >
            <div class="absolute inset-0 -z-10 bg-gradient-to-r from-church-gold-600/35 via-church-gold-500/80 to-church-gold-600/95"></div>

            <div class="w-full py-9 pr-6 pl-[32%] text-center sm:py-10 sm:pr-8 md:pl-[30%] lg:pr-10 lg:pl-[32%] xl:pr-12">
                <h2 class="font-heading text-xl font-bold uppercase tracking-[0.04em] text-white sm:text-2xl">
                    {{ $givingHeading }}
                </h2>
                <p class="mx-auto mt-1.5 max-w-md font-body text-sm leading-6 text-white/95">
                    {{ $givingDescription }}
                </p>
                <a
                    href="{{ route('public.give') }}"
                    wire:navigate
                    class="mt-5 inline-flex min-h-11 items-center justify-center rounded-md bg-church-green-700 px-5 py-2.5 font-heading text-xs font-bold uppercase text-white shadow-sm transition hover:bg-church-green-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-church-green-950"
                >
                    {{ $givingButtonText }}
                </a>
            </div>
        </article>
    </div>
</section>
