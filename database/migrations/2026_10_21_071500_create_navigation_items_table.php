<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bottom-navigation items managed from the Laravel admin/API.
     *
     * The icon *files* live in Cloudinary — only the URLs are stored here,
     * together with the label, the Flutter route key and the display order.
     */
    public function up(): void
    {
        Schema::create('navigation_items', function (Blueprint $table) {
            $table->id();

            // Stable identifier of the button (e.g. "home").
            $table->string('key', 50)->unique();

            // Button caption (English). `label_km` is the optional Khmer caption.
            $table->string('label', 100);
            $table->string('label_km', 100)->nullable();

            // Cloudinary URLs (or bundled asset paths) for the two icon states.
            $table->string('icon_url', 2048);
            $table->string('active_icon_url', 2048)->nullable();

            // Approved Flutter screen identifier, e.g. "home" / "wishlist".
            $table->string('route_key', 50);

            // Display order in the bottom navigation bar.
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Show (true) or hide (false) the button.
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_items');
    }
};
