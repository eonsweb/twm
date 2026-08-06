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
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('receipt_number')->nullable()->unique();
            $table->foreignId('donor_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('donation_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('donation_campaign_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3);
            $table->string('payment_method')->index();
            $table->string('payment_status')->index();
            $table->string('payment_provider')->nullable();
            $table->string('provider_reference')->nullable()->unique();
            $table->string('transaction_reference')->nullable()->index();
            $table->dateTime('donated_at')->index();
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_recurring')->default(false);
            $table->string('recurring_frequency')->nullable();
            $table->string('source')->index();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
