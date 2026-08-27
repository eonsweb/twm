<?php

namespace App\Pages;

use App\Models\Media;
use App\Models\PageSection;

final class HomepageHero
{
    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [
            'variant' => 'anniversary',
            'anniversary_number' => '20',
            'anniversary_unit' => 'YEARS',
            'eyebrow' => 'CELEBRATING 20 YEARS',
            'heading' => '20th Anniversary',
            'script_heading' => 'Celebration',
            'theme' => 'Your Faithfulness and Grace Has Brought Us This Far',
            'description' => '<p>We give all glory to God for two decades of His unfailing love, grace, and faithfulness. Join us as we celebrate His goodness through the years and look forward to greater things ahead.</p>',
            'primary_label' => 'JOIN THE CELEBRATION',
            'primary_url' => route('public.events.show', '20th-anniversary-celebration', false),
            'secondary_label' => 'VIEW ANNIVERSARY EVENTS',
            'secondary_url' => route('public.events.index', ['type' => 'anniversary'], false),
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
        $settings = $this->withDefaults([
            ...($section->settings ?? []),
            ...array_filter([
                'eyebrow' => $section->subheading,
                'heading' => $section->heading,
                'description' => $section->content,
            ], fn (mixed $value): bool => filled($value)),
        ]);
        $emblemMediaId = data_get($settings, 'emblem_media_id');
        $emblem = filled($emblemMediaId)
            ? Media::query()->images()->public()->active()->find((int) $emblemMediaId)
            : null;

        return [
            'settings' => $settings,
            'emblem' => $emblem?->publicImageUrl() ? $emblem : null,
        ];
    }
}
