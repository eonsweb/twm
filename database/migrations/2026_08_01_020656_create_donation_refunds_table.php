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
        Schema::create('donation_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donation_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->text('reason');
            $table->dateTime('refunded_at');
            $table->string('reference')->nullable()->unique();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donation_refunds');
    }
};
