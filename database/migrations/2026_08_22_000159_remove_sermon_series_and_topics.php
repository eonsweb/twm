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
        Schema::dropIfExists('sermon_topic');

        Schema::table('sermons', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sermon_series_id');
        });

        Schema::dropIfExists('sermon_series');
        Schema::dropIfExists('topics');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('sermon_series', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 200);
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->date('starts_at')->nullable()->index();
            $table->date('ends_at')->nullable()->index();
            $table->string('status', 32)->default('draft')->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'starts_at']);
        });

        Schema::create('topics', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('sermons', function (Blueprint $table): void {
            $table->foreignId('sermon_series_id')
                ->nullable()
                ->after('speaker_id')
                ->constrained('sermon_series')
                ->nullOnDelete();
        });

        Schema::create('sermon_topic', function (Blueprint $table): void {
            $table->foreignId('sermon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->primary(['sermon_id', 'topic_id']);
        });
    }
};
