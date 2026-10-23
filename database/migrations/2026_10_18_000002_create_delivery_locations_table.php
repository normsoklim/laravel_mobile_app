<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The delivery_locations table stores GPS history for each delivery.
     * This is useful for troubleshooting, reporting and replaying a
     * delivery route. The deliveries table keeps the latest position
     * (current_latitude/current_longitude) for fast map refreshes.
     */
    public function up(): void
    {
        Schema::create('delivery_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->onDelete('cascade');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy')->nullable(); // GPS accuracy in meters
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index('delivery_id');
            $table->index('recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_locations');
    }
};
