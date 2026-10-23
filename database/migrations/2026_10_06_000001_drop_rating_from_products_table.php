<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The rating is derived from the reviews table
     * (average of the review ratings).
     */
    public function up(): void
    {
        if (Schema::hasColumn('products', 'rating')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('rating');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('products', 'rating')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('rating', 3, 1)->default(0); // 0.0 - 5.0
            });
        }
    }
};
