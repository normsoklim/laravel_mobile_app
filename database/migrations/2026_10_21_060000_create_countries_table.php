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
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            // English country name, e.g. "United States".
            $table->string('name')->unique();
            // Emoji flag rendered next to the country name, e.g. "🇺🇸".
            $table->string('flag', 16)->nullable();
            // ISO 3166-1 alpha-2 code, e.g. "US".
            $table->string('iso_code', 2)->nullable()->index();
            // International dialling prefix, e.g. "+855".
            $table->string('dial_code', 8)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
