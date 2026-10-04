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
        Schema::create('homepage_hero_slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('mobile_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('video_poster_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('emblem_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('media_type', 16)->default('image');
            $table->string('cta_text', 100)->nullable();
            $table->string('cta_url', 2048)->nullable();
            $table->string('secondary_cta_text', 100)->nullable();
            $table->string('secondary_cta_url', 2048)->nullable();
            $table->unsignedTinyInteger('overlay_opacity')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['page_id', 'is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('homepage_hero_slides');
    }
};
