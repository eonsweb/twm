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
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donation_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable()->unique();
            $table->string('payment_method')->index();
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3);
            $table->string('status')->index();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
