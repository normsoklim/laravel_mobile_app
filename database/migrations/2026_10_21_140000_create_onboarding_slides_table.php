<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('onboarding_slides', function (Blueprint $table) {
            $table->id();

            // Stable identifier of the slide (e.g. "welcome").
            $table->string('key', 50)->unique();

            // Cloudinary URL of the slide illustration.
            $table->string('image_url', 2048);

            // Optional captions (English / Khmer) shown under the illustration.
            $table->string('title', 200)->nullable();
            $table->string('title_km', 200)->nullable();
            $table->text('description')->nullable();
            $table->text('description_km')->nullable();

            // Display order in the walkthrough carousel.
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Show (true) or hide (false) the slide.
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_slides');
    }
};
