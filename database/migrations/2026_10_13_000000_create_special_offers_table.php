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
        Schema::create('special_offers', function (Blueprint $table) {
            $table->id()->primary();
            // Marketing headline, e.g. "Today's Special!".
            $table->string('title');
            // Supporting copy, e.g. "Get discount for every order...".
            $table->text('description')->nullable();
            // Percentage off, e.g. 30.00 for "30%".
            $table->decimal('discount_percentage', 5, 2)->default(0);
            // Banner image URL or asset path (e.g. Cloudinary/Firebase URL).
            $table->string('image')->nullable();
            // Validity window. NULL bounds are treated as unbounded.
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            // Soft toggle so an offer can be hidden without deleting it.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('special_offers');
    }
};
