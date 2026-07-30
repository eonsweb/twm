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
        Schema::create('ministry_sermon', function (Blueprint $table) {
            $table->foreignId('ministry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sermon_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['ministry_id', 'sermon_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ministry_sermon');
    }
};
