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
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id()->primary();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            // Cart lines are ephemeral: if the product is deleted the
            // line disappears with it.
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('size')->nullable(); // e.g. "M", "41"
            // Hex color code, e.g. "#FF5733" — the Flutter client
            // converts it to a Color object.
            $table->string('color', 9)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};