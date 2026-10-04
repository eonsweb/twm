@props(['slide', 'first' => false, 'anniversary' => false])
@php
    $main = \App\Models\HomepageHeroSlide::usableMedia($slide->media) ? $slide->media : null;
    $mobile = \App\Models\HomepageHeroSlide::usableMedia($slide->mobileMedia) ? $slide->mobileMedia : null;
    $poster = $slide->videoPosterMedia?->publicImageUrl();
    $variants = $mobile ? ['desktop' => $main, 'mobile' => $mobile] : ['all' => $main];
@endphp
@foreach($variants as $viewport => $media)
    @if($media)
        <div data-hero-media="{{ $viewport }}" @class(['absolute inset-0', 'hidden md:block' => $viewport === 'desktop', 'md:hidden' => $viewport === 'mobile'])>
            @if($media->media_type === \App\MediaType::Video)
                <video data-hero-video data-src="{{ $media->publicUrl() }}" muted playsinline preload="none" @if($poster) poster="{{ $poster }}" @endif class="absolute inset-0 h-full w-full object-cover" aria-hidden="true" tabindex="-1"></video>
            @else
                <picture>
                    @if($viewport !== 'all')
                        <source media="{{ $viewport === 'mobile' ? '(max-width: 767px)' : '(min-width: 768px)' }}" srcset="{{ $media->publicImageUrl() }}">
                    @endif
                    <img src="{{ $viewport === 'all' ? $media->publicImageUrl() : 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=' }}" alt="{{ data_get($slide->settings, 'background_alt') ?: $media->alt_text ?: $media->name }}" @if($media->width) width="{{ $media->width }}" @endif @if($media->height) height="{{ $media->height }}" @endif loading="{{ $first ? 'eager' : 'lazy' }}" fetchpriority="{{ $first ? 'high' : 'auto' }}" @class(['hero-parallax-image absolute inset-x-0 -top-[5%] h-[110%] w-full object-cover', 'object-[68%_center]' => $anniversary, 'object-center' => !$anniversary])>
                </picture>
            @endif
        </div>
    @endif
@endforeach
