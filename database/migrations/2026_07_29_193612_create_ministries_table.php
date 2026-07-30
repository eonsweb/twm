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
        Schema::create('ministries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug')->unique();
            $table->string('short_description', 500)->nullable();
            $table->longText('description')->nullable();
            $table->text('mission')->nullable();
            $table->text('vision')->nullable();
            $table->string('featured_image')->nullable();
            $table->string('logo')->nullable();
            $table->string('meeting_day', 50)->nullable();
            $table->time('meeting_time')->nullable();
            $table->string('meeting_location')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 64)->nullable();
            $table->unsignedInteger('display_order')->default(0)->index();
            $table->string('status', 32)->default('draft')->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['status', 'display_order', 'name']);
            $table->index(['is_featured', 'status', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ministries');
    }
};
