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
        Schema::create('order_trackings', function (Blueprint $table) {
            $table->id()->primary();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            // Status snapshot, e.g. "Pending", "In Delivery", "Delivered".
            $table->string('status')->index();
            // Human-readable description, e.g. "Order is in Packing".
            $table->text('description')->nullable();
            // Optional location label shown in the tracking timeline.
            $table->string('location')->nullable();
            // Date and time the tracking event occurred.
            $table->date('tracking_date')->nullable();
            $table->dateTime('tracking_time')->nullable();
            // Marks the final step of the fulfilment flow.
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_trackings');
    }
};
