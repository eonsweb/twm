<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('featured_image_id')->nullable()->after('featured_image')->constrained('media')->nullOnDelete();
            $table->string('icon', 64)->nullable()->after('featured_image_id');
            $table->string('schedule_type', 24)->default('one_time')->after('timezone')->index();
            $table->unsignedSmallInteger('recurrence_interval')->default(1)->after('recurrence_rule');
            $table->json('recurrence_days')->nullable()->after('recurrence_interval');
            $table->string('recurrence_week_of_month', 16)->nullable()->after('recurrence_days');
            $table->unsignedTinyInteger('recurrence_month')->nullable()->after('recurrence_week_of_month');
            $table->unsignedTinyInteger('recurrence_day_of_month')->nullable()->after('recurrence_month');
            $table->date('recurrence_end_date')->nullable()->after('recurrence_day_of_month')->index();
            $table->text('livestream_url')->nullable()->after('meeting_url');
            $table->boolean('is_active')->default(true)->after('is_featured')->index();
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');

            $table->index(['is_active', 'status', 'schedule_type'], 'events_public_schedule_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_public_schedule_index');
            $table->dropForeign(['featured_image_id']);
            $table->dropColumn([
                'featured_image_id',
                'icon',
                'schedule_type',
                'recurrence_interval',
                'recurrence_days',
                'recurrence_week_of_month',
                'recurrence_month',
                'recurrence_day_of_month',
                'recurrence_end_date',
                'livestream_url',
                'is_active',
                'sort_order',
            ]);
        });
    }
};
