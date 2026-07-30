@props([
    'media',
    'size' => 'md',
])

@php
    $sizes = [
        'sm' => 'size-12',
        'md' => 'size-20',
        'lg' => 'h-44 w-full',
    ];
    $previewUrl = $media->media_type === \App\MediaType::Image
        && $media->status === \App\MediaStatus::Active
        && ! $media->trashed()
        ? route('media.preview', $media)
        : null;
@endphp

<div {{ $attributes->class([
    $sizes[$size] ?? $sizes['md'],
    'flex shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-zinc-400',
]) }}>
    @if ($previewUrl)
        <img
            src="{{ $previewUrl }}"
            alt="{{ $media->alt_text ?: __('Preview of :name', ['name' => $media->name]) }}"
            class="h-full w-full object-cover"
            loading="lazy"
        >
    @else
        <flux:icon :icon="$media->media_type->icon()" class="size-7" />
    @endif
</div>
