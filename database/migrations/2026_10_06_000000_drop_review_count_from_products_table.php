<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The review count is derived from the reviews relationship.
     */
    public function up(): void
    {
        if (Schema::hasColumn('products', 'review_count')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('review_count');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('products', 'review_count')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedInteger('review_count')->default(0);
            });
        }
    }
};
