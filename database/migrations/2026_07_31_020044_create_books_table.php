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
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('subtitle')->nullable();
            $table->string('author_name');
            $table->foreignId('leadership_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('speaker_id')->nullable()->constrained('people')->nullOnDelete();
            $table->longText('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('isbn', 32)->nullable()->index();
            $table->string('publisher')->nullable();
            $table->date('publication_date')->nullable()->index();
            $table->string('edition', 100)->nullable();
            $table->string('language', 64)->default('English');
            $table->unsignedInteger('page_count')->nullable();
            $table->string('format', 32)->index();
            $table->decimal('price', 12, 2)->nullable();
            $table->char('currency', 3)->default('GHS');
            $table->unsignedInteger('stock_quantity')->nullable();
            $table->string('availability_status', 32)->index();
            $table->text('purchase_url')->nullable();
            $table->text('download_url')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_free')->default(false)->index();
            $table->string('status', 32)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['is_featured', 'status', 'published_at']);
            $table->index(['author_name', 'title']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
