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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('post_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('featured_image')->nullable();
            $table->string('featured_image_alt_text', 255)->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->string('visibility', 16)->default('public')->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('allow_comments')->default(false);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('scheduled_for')->nullable()->index();
            $table->timestamp('archived_at')->nullable()->index();
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 170)->nullable();
            $table->text('canonical_url')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'visibility', 'published_at']);
            $table->index(['status', 'visibility', 'scheduled_for']);
            $table->index(['post_category_id', 'status', 'published_at']);
            $table->index(['author_id', 'status']);
            $table->index(['is_featured', 'status', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
