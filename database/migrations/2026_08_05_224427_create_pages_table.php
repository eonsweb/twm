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
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug')->unique();
            $table->string('page_type', 32)->default('standard')->index();
            $table->string('template', 32)->default('default')->index();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->foreignId('featured_image_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('status', 32)->default('draft')->index();
            $table->string('visibility', 32)->default('public')->index();
            $table->boolean('is_homepage')->default(false)->index();
            $table->boolean('show_in_navigation')->default(false)->index();
            $table->string('navigation_label')->nullable();
            $table->unsignedInteger('navigation_order')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->text('canonical_url')->nullable();
            $table->boolean('robots_index')->default(true);
            $table->boolean('robots_follow')->default(true);
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->foreignId('og_image_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'visibility', 'published_at']);
            $table->index(['show_in_navigation', 'navigation_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
