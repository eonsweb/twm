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
        DB::table('events')
            ->where('is_recurring', true)
            ->update([
                'schedule_type' => DB::raw("CASE
                    WHEN recurrence_rule = 'weekly' THEN 'weekly'
                    WHEN recurrence_rule = 'monthly' THEN 'monthly'
                    ELSE 'custom'
                END"),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('events')->update(['schedule_type' => 'one_time']);
    }
};
