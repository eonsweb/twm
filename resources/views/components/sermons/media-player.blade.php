@props(['sermon'])

@php
    $mediaService = app(\App\Sermons\ExternalMedia::class);
    $canEmbed = filled($sermon->embed_url) && $mediaService->isEmbeddableUrl($sermon->embed_url);
@endphp

<div {{ $attributes->class(['overflow-hidden rounded-2xl bg-slate-950 shadow-xl']) }}>
    @if ($canEmbed)
        <div class="aspect-video w-full">
            <iframe
                src="{{ $sermon->embed_url }}"
                title="{{ __('Media player for :title', ['title' => $sermon->title]) }}"
                class="h-full w-full border-0"
                loading="lazy"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                allowfullscreen
                sandbox="allow-scripts allow-same-origin allow-presentation"
                referrerpolicy="strict-origin-when-cross-origin"
            ></iframe>
        </div>
    @else
        <div class="relative flex aspect-video items-center justify-center overflow-hidden">
            @if ($sermon->thumbnailUrl())
                <img src="{{ $sermon->thumbnailUrl() }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-60">
            @endif
            <a
                href="{{ $sermon->external_media_url }}"
                target="_blank"
                rel="noopener noreferrer nofollow"
                class="relative inline-flex items-center gap-2 rounded-full bg-white px-5 py-3 font-semibold text-slate-950 shadow-lg hover:bg-stone-100"
            >
                <flux:icon.play class="size-5" />
                {{ in_array($sermon->media_type, [\App\SermonMediaType::Audio, \App\SermonMediaType::PodcastEpisode], true) ? __('Listen externally') : __('Watch externally') }}
            </a>
        </div>
    @endif
</div>
