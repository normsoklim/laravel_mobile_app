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
        Schema::create('products', function (Blueprint $table) {
            $table->id()->primary();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->string('image'); // Main image URL shown in lists/cards
            $table->json('images')->default('[]'); // Gallery image URLs
            $table->text('description')->nullable();
            $table->unsignedInteger('sold_count')->default(0);
            $table->json('sizes')->default('[]'); // e.g. ["S", "M", "L"]
            $table->json('colors')->default('[]');
            $table->string('category')->nullable()->index();
            $table->unsignedInteger('stock')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};