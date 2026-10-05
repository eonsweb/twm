<?php

namespace App\Pages;

use App\Models\Media;
use App\Models\PageSection;

final class HomepageHero
{
    /** @return list<string> */
    public function editableSettingKeys(): array
    {
        return [
            'variant',
            'hero_video_media_id',
            'hero_poster_media_id',
            'anniversary_number',
            'anniversary_unit',
            'eyebrow',
            'script_heading',
            'theme',
            'emblem_media_id',
            'primary_label',
            'primary_url',
            'secondary_label',
            'secondary_url',
        ];
    }

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [
            'variant' => 'default',
            'anniversary_number' => '20',
            'anniversary_unit' => 'YEARS',
            'eyebrow' => 'WELCOME TO TWM',
            'heading' => 'THE LAND OF OVERFLOW',
            'script_heading' => '',
            'theme' => '',
            'description' => '<p>A place to encounter God, discover purpose and live victoriously.</p>',
            'primary_label' => 'WATCH NOW',
            'primary_url' => route('public.sermons.index', absolute: false),
            'secondary_label' => 'PLAN YOUR VISIT',
            'secondary_url' => '#visit',
            'emblem_media_id' => null,
            'show_emblem' => true,
            'show_theme' => true,
            'show_description' => true,
            'show_primary_cta' => true,
            'show_secondary_cta' => true,
            'show_scroll_indicator' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function withDefaults(array $settings): array
    {
        return [
            ...$this->defaults(),
            ...$settings,
        ];
    }

    /** @return array<string, mixed> */
    public function resolve(PageSection $section): array
    {
        $storedSettings = $section->settings ?? [];
        $isLegacyHomepageHero = ! array_key_exists('variant', $storedSettings)
            && $section->heading === 'Welcome to Triumphant World Ministry'
            && $section->subheading === 'A place to believe, belong, and become';
        $sectionContent = $isLegacyHomepageHero
            ? []
            : array_filter([
                'eyebrow' => $section->subheading,
                'heading' => $section->heading,
                'description' => $section->content,
            ], fn (mixed $value): bool => filled($value));
        $settings = $this->withDefaults([
            ...($isLegacyHomepageHero ? [] : $storedSettings),
            ...$sectionContent,
        ]);
        $emblemMediaId = data_get($settings, 'emblem_media_id');
        $emblem = filled($emblemMediaId)
            ? Media::query()->images()->public()->active()->find((int) $emblemMediaId)
            : null;

        return [
            'settings' => $settings,
            'emblem' => $emblem?->publicImageUrl() ? $emblem : null,
            'video' => $this->video($settings),
            'poster' => Media::query()->images()->public()->active()->find(data_get($settings, 'hero_poster_media_id')),
            'slides' => \App\Models\HomepageHeroSlide::query()->where('page_id', $section->page_id)->visible()->with(['media', 'mobileMedia', 'videoPosterMedia', 'emblemMedia'])->orderBy('sort_order')->orderBy('id')->get(),
        ];
    }

    /** @param array<string, mixed> $settings */
    private function video(array $settings): ?Media
    {
        $media = Media::query()->find(data_get($settings, 'hero_video_media_id'));

        return $media?->media_type === \App\MediaType::Video && \App\Models\HomepageHeroSlide::usableMedia($media)
            ? $media : null;
    }
}
