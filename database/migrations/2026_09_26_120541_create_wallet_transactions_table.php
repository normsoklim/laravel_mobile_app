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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id()->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Optional link to the order that generated this transaction.
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            // Tile heading (e.g. "Ordered 3 items", "Wallet Top Up").
            $table->string('title', 100);
            // Display type consumed by the Flutter wallet screen
            // ('Orders' or 'Top Up' — the UI checks type == 'top up').
            $table->string('type', 20);
            // Always positive; the sign is derived from the type in the UI.
            $table->decimal('amount', 10, 2);
            // Snapshot image (e.g. first ordered product); null for top-ups.
            $table->text('image_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
