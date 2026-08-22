<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $homepageSettings = DB::table('system_settings')
            ->where('group', 'homepage')
            ->whereIn('key', [
                'welcome_heading',
                'welcome_message',
                'welcome_body',
                'welcome_signature',
                'welcome_pastor_name',
                'welcome_pastor_role',
                'welcome_pastor_title',
                'welcome_image_id',
                'welcome_image_alt',
            ])
            ->pluck('value', 'key');

        DB::table('pages')
            ->where('is_homepage', true)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->each(function (object $homepage) use ($homepageSettings): void {
                $existingWelcome = DB::table('page_sections')
                    ->where('page_id', $homepage->id)
                    ->where('section_type', 'welcome')
                    ->oldest('id')
                    ->first();

                if ($existingWelcome) {
                    return;
                }

                $legacyWelcome = DB::table('page_sections')
                    ->where('page_id', $homepage->id)
                    ->where('section_type', 'welcome-upcoming-event')
                    ->oldest('id')
                    ->first();

                if ($legacyWelcome) {
                    if ($legacyWelcome->deleted_at !== null) {
                        return;
                    }

                    DB::table('page_sections')
                        ->where('id', $legacyWelcome->id)
                        ->update([
                            'section_type' => 'welcome',
                            'name' => in_array($legacyWelcome->name, [
                                'Welcome, sermon, and upcoming events',
                                'Welcome, sermon, and events',
                                'Legacy welcome band',
                            ], true) ? 'Welcome section' : $legacyWelcome->name,
                            'updated_at' => now(),
                        ]);

                    return;
                }

                $serviceTimesOrder = DB::table('page_sections')
                    ->where('page_id', $homepage->id)
                    ->whereNull('deleted_at')
                    ->where('section_type', 'service-times')
                    ->value('sort_order');
                $heroOrder = DB::table('page_sections')
                    ->where('page_id', $homepage->id)
                    ->whereNull('deleted_at')
                    ->where('section_type', 'hero')
                    ->value('sort_order');
                $sortOrder = $serviceTimesOrder !== null
                    ? (int) $serviceTimesOrder + 10
                    : ($heroOrder !== null ? (int) $heroOrder + 10 : 10);

                DB::table('page_sections')
                    ->where('page_id', $homepage->id)
                    ->whereNull('deleted_at')
                    ->where('sort_order', '>=', $sortOrder)
                    ->increment('sort_order', 10);

                $welcomeImageId = $homepageSettings->get('welcome_image_id');
                $welcomeImageId = filled($welcomeImageId)
                    ? DB::table('media')->where('id', (int) $welcomeImageId)->whereNull('deleted_at')->value('id')
                    : null;
                $settings = array_filter([
                    'welcome_signature' => $homepageSettings->get('welcome_signature'),
                    'welcome_pastor_name' => $homepageSettings->get('welcome_pastor_name'),
                    'welcome_pastor_role' => $homepageSettings->get('welcome_pastor_role') ?: $homepageSettings->get('welcome_pastor_title'),
                    'pastor_image_alt' => $homepageSettings->get('welcome_image_alt'),
                ], fn (mixed $value): bool => filled($value));

                DB::table('page_sections')->insert([
                    'page_id' => $homepage->id,
                    'section_type' => 'welcome',
                    'name' => 'Welcome section',
                    'heading' => $homepageSettings->get('welcome_heading') ?: 'Welcome Home!',
                    'subheading' => null,
                    'content' => $homepageSettings->get('welcome_message') ?: $homepageSettings->get('welcome_body'),
                    'settings' => json_encode($settings, JSON_THROW_ON_ERROR),
                    'sort_order' => $sortOrder,
                    'is_visible' => true,
                    'background_image_id' => $welcomeImageId,
                    'created_by' => $homepage->created_by,
                    'updated_by' => $homepage->updated_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]);
            });
    }

    /**
     * Existing administrator content is intentionally preserved on rollback.
     */
    public function down(): void {}
};
