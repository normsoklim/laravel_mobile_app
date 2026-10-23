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
        // One row per payment attempt (KHQR / Bakong). The existing
        // `payments` table stores payment *methods* (cash / card / khqr),
        // while this table stores actual payment *transactions* tied to
        // an order.
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            // e.g. "bakong_khqr"
            $table->string('method', 30)->default('bakong_khqr');

            // pending | paid | failed | expired | cancelled
            $table->string('status', 20)->default('pending');

            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');

            // Bakong transaction hash once the payment is confirmed.
            $table->string('transaction_id')->nullable();

            // MD5 returned when the KHQR is generated — used to look up
            // the transaction on Bakong.
            $table->string('md5')->nullable()->index();

            // Raw KHQR payload string rendered as a QR code by the client.
            $table->text('qr')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            // Raw Bakong responses / extra info for debugging & audits.
            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

 
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};