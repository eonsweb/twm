@props(['media'])

<flux:badge :color="match ($media->media_type) {
    \App\MediaType::Image => 'amber',
    \App\MediaType::Video => 'violet',
    \App\MediaType::Audio => 'emerald',
    \App\MediaType::Archive => 'zinc',
    default => 'blue',
}">
    {{ $media->media_type->label() }}
</flux:badge>
