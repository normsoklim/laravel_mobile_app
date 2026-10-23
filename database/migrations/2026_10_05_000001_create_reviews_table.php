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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            // One review per order line (unique below).
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Kept nullable: if the product is deleted later, the
            // review still survives thanks to the snapshot fields
            // on the order item.
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->tinyInteger('rating'); // 1..5
            $table->text('comment')->nullable();
            $table->json('image_paths')->nullable();
            $table->timestamps();

            $table->unique('order_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
