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
        Schema::create('prayer_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 32)->unique();
            $table->ulid('public_token')->unique();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('subject');
            $table->longText('request');
            $table->string('category', 32)->nullable()->index();
            $table->string('submission_type', 16)->default('identified');
            $table->string('privacy_level', 32)->default('private')->index();
            $table->string('status', 32)->default('new')->index();
            $table->string('priority', 16)->default('normal')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->timestamp('answered_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable()->index();
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('allow_contact')->default(false);
            $table->boolean('allow_publication')->default(false);
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('public_title')->nullable();
            $table->text('public_excerpt')->nullable();
            $table->longText('public_content')->nullable();
            $table->longText('admin_notes')->nullable();
            $table->string('source', 32)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'priority', 'created_at']);
            $table->index(['assigned_to', 'status']);
            $table->index(['is_published', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prayer_requests');
    }
};
