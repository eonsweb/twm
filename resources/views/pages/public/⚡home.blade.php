<?php

use App\ViewModels\HomepageContent;
use App\Models\Page;
use App\Pages\SectionDataResolver;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.public')] class extends Component
{
    /** @var array<string, mixed> */
    public array $home = [];
    public ?Page $managedPage = null;
    public array $sectionData = [];

    public function mount(HomepageContent $content, SectionDataResolver $resolver): void
    {
        $this->home = $content->build();
        $this->managedPage = Page::query()->publiclyVisible()->where('is_homepage', true)->with(['sections' => fn ($query) => $query->where('is_visible', true)->with('backgroundImage'), 'ogImage'])->first();
        if ($this->managedPage) {
            foreach ($this->managedPage->sections as $section) {
                $this->sectionData[$section->id] = $resolver->resolve($section);
            }
        }
    }
};
?>

@php
    $settings = $home['settings'];
    $homepage = $settings['homepage'] ?? [];
    $general = $settings['general'] ?? [];
    $church = $settings['church'] ?? [];
    $enabled = $home['enabledSections'];
    $description = $managedPage?->meta_description ?? $managedPage?->excerpt ?? $general['meta_description'] ?? $homepage['hero_description'] ?? '';
    $structuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'Church',
        'name' => $church['official_name'] ?? config('app.name'),
        'url' => route('home'),
        'description' => $description,
        'telephone' => data_get($settings, 'contact.primary_phone'),
        'email' => data_get($settings, 'contact.primary_email'),
        'address' => data_get($settings, 'contact.physical_address'),
    ];
@endphp

@push('meta')
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ route('home') }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $general['website_name'] ?? config('app.name') }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ route('home') }}">
    @if ($home['socialImageUrl'])<meta property="og:image" content="{{ $home['socialImageUrl'] }}">@endif
    <meta name="twitter:card" content="summary_large_image">
    <script type="application/ld+json">{!! json_encode($structuredData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !!}</script>
@endpush

@if ($managedPage)
<article class="overflow-hidden bg-white">
    <header class="bg-church-maroon-950 py-16 text-white sm:py-24"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"><h1 class="text-4xl font-bold tracking-tight sm:text-6xl">{{ $managedPage->title }}</h1>@if($managedPage->excerpt)<p class="mt-5 max-w-3xl text-lg text-white/80">{{ $managedPage->excerpt }}</p>@endif</div></header>
    @if($managedPage->content)<div class="prose mx-auto max-w-4xl px-4 py-12 sm:px-6">{!! app(\App\Blog\HtmlSanitizer::class)->sanitize($managedPage->content) !!}</div>@endif
    @foreach($managedPage->sections as $section)<x-public.page-section :section="$section" :data="$sectionData[$section->id] ?? []" wire:key="homepage-section-{{ $section->id }}" />@endforeach
</article>
@else
<div class="overflow-hidden bg-white">
    <x-public.home.hero :settings="$settings" :image-url="$home['heroImageUrl']" />

    @if (in_array('services', $enabled, true))
        <x-public.home.services :schedules="$home['serviceSchedules']" :settings="$settings" />
    @endif

    @if (in_array('welcome', $enabled, true))
        <x-public.home.welcome :leader="$home['welcomeLeader']" :settings="$settings" />
    @endif

    @if (in_array('sermon_events', $enabled, true))
        <x-public.home.sermon-events :sermon="$home['latestSermon']" :events="$home['upcomingEvents']" />
    @endif

    @if (in_array('ministries', $enabled, true))
        <x-public.home.ministries :ministries="$home['ministries']" />
    @endif

    @if (in_array('calls_to_action', $enabled, true))
        <x-public.home.calls-to-action :settings="$settings" />
    @endif

    @if (in_array('featured_book', $enabled, true) || in_array('testimonials', $enabled, true))
        <div class="mx-auto grid max-w-7xl gap-6 px-4 py-12 sm:px-6 lg:grid-cols-12 lg:px-8">
            @if (in_array('featured_book', $enabled, true))
                <x-public.home.featured-book :book="$home['featuredBook']" class="lg:col-span-5" />
            @endif
            @if (in_array('testimonials', $enabled, true))
                <x-public.home.testimonials :testimonials="$home['testimonials']" class="lg:col-span-7" />
            @endif
        </div>
    @endif
</div>
@endif
