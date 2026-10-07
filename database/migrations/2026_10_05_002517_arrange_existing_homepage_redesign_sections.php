<?php

use App\Pages\PrayerGivingSection;
use App\Settings\SettingManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pages')->where('is_homepage', true)->whereNull('deleted_at')->orderBy('id')->each(function (object $page): void {
            DB::transaction(function () use ($page): void {
                if (! DB::table('page_sections')->where('page_id', $page->id)->where('section_type', 'next-steps')->exists()) {
                    DB::table('page_sections')->insert([
                        'page_id' => $page->id,
                        'section_type' => 'next-steps',
                        'name' => 'Next Steps',
                        'heading' => 'Find your place. Live your purpose.',
                        'subheading' => 'Take your next step',
                        'settings' => '{}',
                        'sort_order' => 40,
                        'is_visible' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                if (! DB::table('page_sections')->where('page_id', $page->id)->where('section_type', 'prayer-giving')->exists()) {
                    $settings = app(PrayerGivingSection::class)->defaults();
                    $manager = app(SettingManager::class);
                    foreach (['prayer', 'giving'] as $key) {
                        $settings[$key.'_heading'] = $manager->get('homepage', $key.'_heading', $settings[$key.'_heading']);
                        $settings[$key.'_description'] = $manager->get('homepage', $key.'_body', $settings[$key.'_description']);
                    }
                    DB::table('page_sections')->insert([
                        'page_id' => $page->id,
                        'section_type' => 'prayer-giving',
                        'name' => 'Prayer and Giving',
                        'settings' => json_encode($settings, JSON_THROW_ON_ERROR),
                        'sort_order' => 90,
                        'is_visible' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $order = array_flip(['hero', 'welcome', 'welcome-upcoming-event', 'service-times', 'next-steps', 'upcoming-events', 'ministries-grid', 'featured-sermons', 'prayer-giving']);
                $sections = DB::table('page_sections')->where('page_id', $page->id)->whereNull('deleted_at')->orderBy('sort_order')->orderBy('id')->get();
                $sections = $sections->sortBy(fn (object $section): int => $order[$section->section_type] ?? 100)->values();

                foreach ($sections as $position => $section) {
                    DB::table('page_sections')->where('id', $section->id)->update(['sort_order' => ($position + 1) * 10]);
                }
            });
        });
    }

    public function down(): void
    {
        // Preserve administrator content and subsequent ordering changes on rollback.
    }
};
