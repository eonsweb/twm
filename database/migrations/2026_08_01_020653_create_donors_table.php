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
        Schema::create('donors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name')->default('');
            $table->string('email')->nullable()->index();
            $table->string('phone', 50)->nullable()->index();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country', 100)->nullable();
            $table->string('donor_type')->index();
            $table->text('notes')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donors');
    }
};
