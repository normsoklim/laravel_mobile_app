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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id()->primary();
            // Each address belongs to a user.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Label shown on the address card (e.g. Home, Work).
            $table->string('label', 50);
            // Person receiving the delivery.
            $table->string('recipient_name', 100);
            $table->string('phone', 30);
            $table->string('street');
            $table->string('city', 100);
            // Only one default address per user (enforced in AddressController).
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
