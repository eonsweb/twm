<?php

namespace App\Pages;

use App\Models\Media;
use App\Models\PageSection;
use Illuminate\Support\Arr;

final class PrayerGivingSection
{
    /** @return array<string, string|null> */
    public function defaults(): array
    {
        return [
            'prayer_heading' => 'Need Prayer?',
            'prayer_description' => 'We would love to stand with you in prayer.',
            'prayer_button_text' => 'Submit Prayer Request',
            'prayer_background_media_id' => null,
            'giving_heading' => 'Partner With the Work of God',
            'giving_description' => "Your giving supports lives, spreads the Gospel, and advances God's Kingdom.",
            'giving_button_text' => 'Give Online',
            'giving_background_media_id' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function withDefaults(array $settings): array
    {
        $defaults = $this->defaults();
        $configured = array_filter(
            Arr::only($settings, array_keys($defaults)),
            fn (mixed $value): bool => filled($value),
        );

        return [...$defaults, ...$configured];
    }

    /** @return array<string, mixed> */
    public function resolve(PageSection $section): array
    {
        $settings = $this->withDefaults($section->settings ?? []);
        $media = Media::query()
            ->whereKey(array_filter([
                data_get($settings, 'prayer_background_media_id'),
                data_get($settings, 'giving_background_media_id'),
            ]))
            ->images()
            ->public()
            ->active()
            ->get()
            ->keyBy('id');

        $prayerMedia = $media->get((int) data_get($settings, 'prayer_background_media_id'));
        $givingMedia = $media->get((int) data_get($settings, 'giving_background_media_id'));

        return [
            'settings' => $settings,
            'prayerImageUrl' => $prayerMedia?->publicImageUrl(),
            'givingImageUrl' => $givingMedia?->publicImageUrl(),
        ];
    }
}
