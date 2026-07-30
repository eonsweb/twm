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
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_folder_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 255);
            $table->string('original_name', 255);
            $table->string('file_name', 255);
            $table->string('slug', 280)->nullable()->index();
            $table->string('disk', 64);
            $table->string('directory', 500);
            $table->string('path', 500);
            $table->string('mime_type', 160)->index();
            $table->string('extension', 16)->index();
            $table->string('media_type', 32)->index();
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->string('alt_text', 500)->nullable();
            $table->text('caption')->nullable();
            $table->longText('description')->nullable();
            $table->string('copyright', 255)->nullable();
            $table->string('credit', 255)->nullable();
            $table->text('source_url')->nullable();
            $table->string('visibility', 16)->default('public')->index();
            $table->string('status', 16)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedBigInteger('download_count')->default(0);
            $table->timestamp('last_used_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['disk', 'path']);
            $table->index(['media_folder_id', 'status', 'created_at']);
            $table->index(['media_type', 'status', 'created_at']);
            $table->index(['visibility', 'status', 'created_at']);
            $table->index(['uploaded_by', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
