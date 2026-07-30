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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('featured_image')->nullable();
            $table->string('location_type', 16)->default('physical')->index();
            $table->string('venue_name')->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('country', 100)->default('Ghana');
            $table->text('location_url')->nullable();
            $table->text('meeting_url')->nullable();
            $table->text('registration_url')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 64)->nullable();
            $table->string('contact_email')->nullable();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->string('timezone', 64)->default('Africa/Accra');
            $table->boolean('is_all_day')->default(false);
            $table->boolean('is_recurring')->default(false)->index();
            $table->text('recurrence_rule')->nullable();
            $table->boolean('registration_required')->default(false)->index();
            $table->timestamp('registration_deadline')->nullable();
            $table->unsignedInteger('maximum_attendees')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_livestreamed')->default(false)->index();
            $table->string('status', 32)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['status', 'starts_at']);
            $table->index(['event_type_id', 'starts_at']);
            $table->index(['is_featured', 'status', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
