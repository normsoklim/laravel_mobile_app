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
        Schema::create('orders', function (Blueprint $table) {
            $table->id()->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Human-friendly id shown in the Flutter UI, e.g. "ORD-1727345678901-A1B2"
            // (the Flutter Order.id is this string).
            $table->string('order_number')->unique();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->text('address'); // Full address text from the selected address
            // Values used by the Flutter app: "Cash on Delivery", "Card", "KHQR".
            $table->string('payment_method');
            // Values used by the Flutter app: Pending, In Delivery,
            // Completed, Delivered (plus Cancelled for removed orders).
            $table->string('status')->default('Pending')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};