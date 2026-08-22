<?php

use App\ViewModels\HomepageContent;
use App\Models\Page;
use App\Models\PageSection;
use App\PageSectionType;
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
                $this->sectionData[$section->id] = $this->dataForSection($section, $resolver);
            }
        }
    }

    /** @return array<string, mixed> */
    private function dataForSection(PageSection $section, SectionDataResolver $resolver): array
    {
        if ($section->section_type === PageSectionType::ServiceTimes) {
            return ['items' => $this->home['serviceSchedules']];
        }

        if ($section->section_type === PageSectionType::Welcome) {
            return [
                'settings' => $this->home['settings'],
                'leader' => $this->home['welcomeLeader'],
            ];
        }

        if ($section->section_type === PageSectionType::FeaturedSermons
            && blank(data_get($section->settings, 'speaker_id'))) {
            return ['items' => collect([$this->home['latestSermon']])->filter()];
        }

        if ($section->section_type === PageSectionType::UpcomingEvents
            && blank(data_get($section->settings, 'event_type_id'))) {
            $limit = min(24, max(1, (int) data_get($section->settings, 'limit', 3)));

            return [
                'items' => $this->home['upcomingEvents']->take($limit),
                'eventType' => null,
                'viewAllUrl' => route('public.events.index'),
            ];
        }

        return $resolver->resolve($section);
    }
};
?>

@php
    $settings = $home['settings'];
    $homepage = $settings['homepage'] ?? [];
    $general = $settings['general'] ?? [];
    $church = $settings['church'] ?? [];
    $enabled = $home['enabledSections'];
    $managedHero = $managedPage?->sections->first(fn ($section) => $section->section_type->value === 'hero');
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

<div>
@if ($managedPage)
<article class="overflow-hidden bg-white" aria-label="{{ $managedPage->title }}">
    @unless($managedHero)
        <header class="bg-church-maroon-950 py-16 text-white sm:py-24"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"><h1 class="text-4xl font-bold tracking-tight sm:text-6xl">{{ $managedPage->title }}</h1>@if($managedPage->excerpt)<p class="mt-5 max-w-3xl text-lg text-white/80">{{ $managedPage->excerpt }}</p>@endif</div></header>
    @endunless
    @if($managedPage->content && trim(strip_tags($managedPage->content)) !== 'Content for this page can be managed from the Pages administration module.')<div class="prose mx-auto max-w-4xl px-4 py-12 sm:px-6">{!! app(\App\Blog\HtmlSanitizer::class)->sanitize($managedPage->content) !!}</div>@endif
    @foreach($managedPage->sections as $section)
        @if($section->section_type === PageSectionType::Hero)
            <x-public.home.hero :section="$section" :page-title="$managedPage->title" wire:key="homepage-section-{{ $section->id }}" />
        @else
            <x-public.page-section :section="$section" :data="$sectionData[$section->id] ?? []" wire:key="homepage-section-{{ $section->id }}" />
        @endif
    @endforeach
</article>
@else
<div class="overflow-hidden bg-white">
    <x-public.home.hero :settings="$settings" :image-url="$home['heroImageUrl']" />

    @if (in_array('services', $enabled, true))
        <x-public.home.services :schedules="$home['serviceSchedules']" :settings="$settings" />
    @endif

    @if (collect(['welcome_upcoming_event', 'welcome', 'sermon_events'])->contains(fn (string $section): bool => in_array($section, $enabled, true)))
        <x-public.home.welcome-upcoming-event
            :leader="$home['welcomeLeader']"
            :settings="$settings"
            :sermon="$home['latestSermon']"
            :events="$home['upcomingEvents']"
        />
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
</div>
