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
        Schema::create('mediables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained()->cascadeOnDelete();
            $table->string('mediable_type');
            $table->unsignedBigInteger('mediable_id');
            $table->string('collection', 64)->default('default');
            $table->unsignedInteger('position')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['mediable_type', 'mediable_id', 'collection']);
            $table->index(['media_id', 'collection']);
            $table->unique(
                ['media_id', 'mediable_type', 'mediable_id', 'collection'],
                'mediables_unique_assignment',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mediables');
    }
};
