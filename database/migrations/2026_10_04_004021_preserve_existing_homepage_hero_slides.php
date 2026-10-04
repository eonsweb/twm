<?php

use App\Models\PageSection;
use App\Pages\HomepageHero;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        PageSection::query()->where('section_type', 'hero')
            ->whereHas('page', fn ($query) => $query->where('is_homepage', true))
            ->orderBy('sort_order')->each(function (PageSection $section): void {
                if (DB::table('homepage_hero_slides')->where('page_id', $section->page_id)->exists()) {
                    return;
                }

                $settings = app(HomepageHero::class)->resolve($section)['settings'];
                DB::table('homepage_hero_slides')->insert([
                    'page_id' => $section->page_id,
                    'title' => $settings['heading'],
                    'description' => $settings['description'],
                    'media_id' => $section->background_image_id,
                    'emblem_media_id' => $settings['emblem_media_id'] ?? null,
                    'media_type' => 'image',
                    'cta_text' => $settings['primary_label'] ?? null,
                    'cta_url' => $settings['primary_url'] ?? null,
                    'secondary_cta_text' => $settings['secondary_label'] ?? null,
                    'secondary_cta_url' => $settings['secondary_url'] ?? null,
                    'sort_order' => 0,
                    'is_active' => $section->is_visible,
                    'settings' => json_encode($settings, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Keep migrated content; the schema migration owns removal of the table.
    }
};
