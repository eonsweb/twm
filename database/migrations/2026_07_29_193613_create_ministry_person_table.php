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
        Schema::create('ministry_person', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ministry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->string('role_title')->nullable();
            $table->boolean('is_primary')->default(false)->index();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['ministry_id', 'person_id']);
            $table->index(['ministry_id', 'is_primary', 'display_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ministry_person');
    }
};
