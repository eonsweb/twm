<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_submissions', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_number', 32)->unique();
            $table->ulid('public_token')->unique();
            $table->uuid('submission_token')->unique();
            $table->char('duplicate_key', 64)->unique();
            $table->string('name', 150);
            $table->string('email')->index();
            $table->string('phone', 30)->nullable();
            $table->string('subject', 200);
            $table->string('category', 32)->index();
            $table->text('message');
            $table->string('preferred_contact_method', 32);
            $table->string('status', 32)->default('new')->index();
            $table->string('priority', 16)->default('normal')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('admin_replied_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('read_at')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('source_page')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'priority', 'created_at']);
            $table->index(['assigned_to', 'status']);
            $table->index(['category', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_submissions');
    }
};
