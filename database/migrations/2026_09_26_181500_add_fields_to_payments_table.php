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
        // Adds the payment method fields to the payments table created
        // by 2026_09_26_111900_create_payments_table.
        Schema::table('payments', function (Blueprint $table) {
            // Payment type key matching the Flutter PaymentType enum:
            // cash_on_delivery | card | khqr
            $table->string('type', 30)->unique();
            // Display label, e.g. "KHQR / Bakong" (Flutter PaymentMethod.label).
            $table->string('label');
            $table->string('description')->nullable();
            // Material icon name used by the Flutter client,
            // e.g. "qr_code_2_outlined" (Flutter PaymentMethod.icon).
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['type']);
            $table->dropColumn(['type', 'label', 'description', 'icon', 'is_active']);
        });
    }
};