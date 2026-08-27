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
            ->select('page_sections.id', 'page_sections.settings')
            ->orderBy('page_sections.id')
            ->each(function (object $hero): void {
                $settings = json_decode($hero->settings ?: '{}', true, 512, JSON_THROW_ON_ERROR);

                DB::table('page_sections')->where('id', $hero->id)->update([
                    'settings' => json_encode([
                        ...$settings,
                        'variant' => $settings['variant'] ?? 'anniversary',
                        'anniversary_number' => $settings['anniversary_number'] ?? '20',
                        'anniversary_unit' => $settings['anniversary_unit'] ?? 'YEARS',
                    ], JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * Existing administrator content is intentionally preserved on rollback.
     */
    public function down(): void {}
};
