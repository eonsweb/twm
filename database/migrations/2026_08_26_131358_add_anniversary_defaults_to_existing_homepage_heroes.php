<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('page_sections')
            ->join('pages', 'pages.id', '=', 'page_sections.page_id')
            ->where('pages.is_homepage', true)
            ->where('page_sections.section_type', 'hero')
            ->whereNull('pages.deleted_at')
            ->whereNull('page_sections.deleted_at')
            ->select('page_sections.*')
            ->orderBy('page_sections.id')
            ->each(function (object $hero): void {
                $settings = json_decode($hero->settings ?: '{}', true, 512, JSON_THROW_ON_ERROR);
                $legacyPrimaryLabels = ['Plan Your Visit', 'Visit Us'];
                $legacySecondaryLabels = ['Watch Sermons', 'Watch Live'];
                $hasLegacyPrimary = in_array($settings['primary_label'] ?? null, $legacyPrimaryLabels, true);
                $hasLegacySecondary = in_array($settings['secondary_label'] ?? null, $legacySecondaryLabels, true);

                $settings = [
                    ...$settings,
                    'script_heading' => $settings['script_heading'] ?? 'Celebration',
                    'theme' => $settings['theme'] ?? 'Your Faithfulness and Grace Has Brought Us This Far',
                    'primary_label' => $hasLegacyPrimary ? 'JOIN THE CELEBRATION' : ($settings['primary_label'] ?? 'JOIN THE CELEBRATION'),
                    'primary_url' => $hasLegacyPrimary ? '/events/20th-anniversary-celebration' : ($settings['primary_url'] ?? '/events/20th-anniversary-celebration'),
                    'secondary_label' => $hasLegacySecondary ? 'VIEW ANNIVERSARY EVENTS' : ($settings['secondary_label'] ?? 'VIEW ANNIVERSARY EVENTS'),
                    'secondary_url' => $hasLegacySecondary ? '/events?type=anniversary' : ($settings['secondary_url'] ?? '/events?type=anniversary'),
                    'show_emblem' => $settings['show_emblem'] ?? true,
                    'show_theme' => $settings['show_theme'] ?? true,
                    'show_description' => $settings['show_description'] ?? true,
                    'show_primary_cta' => $settings['show_primary_cta'] ?? true,
                    'show_secondary_cta' => $settings['show_secondary_cta'] ?? true,
                    'show_scroll_indicator' => $settings['show_scroll_indicator'] ?? true,
                ];
                $legacyHeadings = ['Welcome to Triumphant World Ministry', 'Welcome Home'];
                $legacySubheadings = ['A place to believe, belong, and become', 'Believe, belong, become'];
                $legacyDescriptions = [
                    '<p>Join us as we worship Jesus, grow in faith, and serve our world.</p>',
                    '<p>Join us this Sunday.</p>',
                ];

                DB::table('page_sections')->where('id', $hero->id)->update([
                    'heading' => blank($hero->heading) || in_array($hero->heading, $legacyHeadings, true)
                        ? '20th Anniversary'
                        : $hero->heading,
                    'subheading' => blank($hero->subheading) || in_array($hero->subheading, $legacySubheadings, true)
                        ? 'CELEBRATING 20 YEARS'
                        : $hero->subheading,
                    'content' => blank($hero->content) || in_array($hero->content, $legacyDescriptions, true)
                        ? '<p>We give all glory to God for two decades of His unfailing love, grace, and faithfulness. Join us as we celebrate His goodness through the years and look forward to greater things ahead.</p>'
                        : $hero->content,
                    'settings' => json_encode($settings, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * Existing administrator content is intentionally preserved on rollback.
     */
    public function down(): void {}
};
