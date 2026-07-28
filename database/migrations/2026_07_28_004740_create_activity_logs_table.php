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
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('log_name', 64)->index();
            $table->string('event', 96)->index();
            $table->text('description');
            $table->nullableMorphs('subject');
            $table->nullableMorphs('causer');
            $table->uuid('batch_id')->nullable()->index();
            $table->uuid('request_id')->nullable()->index();
            $table->json('properties')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->text('request_url')->nullable();
            $table->string('http_method', 10)->nullable();
            $table->string('route_name')->nullable()->index();
            $table->string('origin', 32)->default('system')->index();
            $table->string('status', 32)->default('success')->index();
            $table->text('failure_reason')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['log_name', 'created_at']);
            $table->index(['event', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
