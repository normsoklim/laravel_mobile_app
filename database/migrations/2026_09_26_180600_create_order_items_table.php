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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id()->primary();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            // Kept nullable: if the product is deleted later, the order
            // history still survives thanks to the snapshot fields below.
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            // Snapshot fields so order history survives product changes.
            $table->string('name');
            $table->text('image')->nullable();
            $table->string('size')->nullable(); // e.g. "M", "41"
            // Hex color code, e.g. "#FF5733" — the Flutter client
            // converts it to a Color object.
            $table->string('color', 9)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price', 10, 2); // Unit price at purchase time
            // No timestamps: line items share the order's created_at
            // (the OrderItem model sets $timestamps = false).
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};