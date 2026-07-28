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
        Schema::create('sermons', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->string('scripture_reference')->nullable();
            $table->date('sermon_date')->index();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('external_media_url');
            $table->string('media_platform', 32)->index();
            $table->string('media_type', 32)->index();
            $table->text('embed_url')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->text('external_thumbnail_url')->nullable();
            $table->foreignId('speaker_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('sermon_series_id')->nullable()->constrained('sermon_series')->nullOnDelete();
            $table->string('service_name')->nullable();
            $table->string('location')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedInteger('display_order')->default(0);
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 170)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['status', 'scheduled_at']);
            $table->index(['status', 'sermon_date']);
            $table->index(['is_featured', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sermons');
    }
};
