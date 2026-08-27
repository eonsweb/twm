<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $homepageSettings = DB::table('system_settings')
            ->where('group', 'homepage')
            ->whereIn('key', ['prayer_heading', 'prayer_body', 'giving_heading', 'giving_body', 'enabled_sections'])
            ->pluck('value', 'key');
        $enabledSectionsValue = $homepageSettings->get('enabled_sections');
        $enabledSections = $enabledSectionsValue === null ? null : json_decode($enabledSectionsValue, true);
        $isVisible = ! is_array($enabledSections)
            || in_array('prayer_giving', $enabledSections, true)
            || in_array('calls_to_action', $enabledSections, true);

        DB::table('pages')
            ->where('is_homepage', true)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->each(function (object $homepage) use ($homepageSettings, $isVisible): void {
                $exists = DB::table('page_sections')
                    ->where('page_id', $homepage->id)
                    ->where('section_type', 'prayer-giving')
                    ->exists();

                if ($exists) {
                    return;
                }

                $featuredBookOrder = DB::table('page_sections')
                    ->where('page_id', $homepage->id)
                    ->whereNull('deleted_at')
                    ->where('section_type', 'featured-book')
                    ->value('sort_order');
                $sortOrder = $featuredBookOrder === null
                    ? (int) DB::table('page_sections')
                        ->where('page_id', $homepage->id)
                        ->whereNull('deleted_at')
                        ->max('sort_order') + 10
                    : (int) $featuredBookOrder;

                if ($featuredBookOrder !== null) {
                    DB::table('page_sections')
                        ->where('page_id', $homepage->id)
                        ->whereNull('deleted_at')
                        ->where('sort_order', '>=', $sortOrder)
                        ->increment('sort_order', 10);
                }

                $prayerHeading = $homepageSettings->get('prayer_heading') ?: 'Need Prayer?';
                $givingHeading = $homepageSettings->get('giving_heading') ?: 'Partner With the Work of God';

                DB::table('page_sections')->insert([
                    'page_id' => $homepage->id,
                    'section_type' => 'prayer-giving',
                    'name' => 'Prayer and giving',
                    'heading' => $prayerHeading.' / '.$givingHeading,
                    'subheading' => null,
                    'content' => null,
                    'settings' => json_encode([
                        'prayer_heading' => $prayerHeading,
                        'prayer_description' => $homepageSettings->get('prayer_body') ?: 'We would love to stand with you in prayer.',
                        'prayer_button_text' => 'Submit Prayer Request',
                        'giving_heading' => $givingHeading,
                        'giving_description' => $homepageSettings->get('giving_body') ?: "Your giving supports lives, spreads the Gospel, and advances God's Kingdom.",
                        'giving_button_text' => 'Give Online',
                    ], JSON_THROW_ON_ERROR),
                    'sort_order' => $sortOrder,
                    'is_visible' => $isVisible,
                    'background_image_id' => null,
                    'created_by' => $homepage->created_by,
                    'updated_by' => $homepage->updated_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Existing administrator content is intentionally preserved on rollback.
    }
};
