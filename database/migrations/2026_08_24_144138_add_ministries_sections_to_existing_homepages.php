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
        DB::table('pages')
            ->where('is_homepage', true)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->each(function (object $homepage): void {
                $exists = DB::table('page_sections')
                    ->where('page_id', $homepage->id)
                    ->where('section_type', 'ministries-grid')
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

                DB::table('page_sections')->insert([
                    'page_id' => $homepage->id,
                    'section_type' => 'ministries-grid',
                    'name' => 'Ministries carousel',
                    'heading' => 'Our Ministries',
                    'subheading' => null,
                    'content' => null,
                    'settings' => json_encode([], JSON_THROW_ON_ERROR),
                    'sort_order' => $sortOrder,
                    'is_visible' => true,
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
