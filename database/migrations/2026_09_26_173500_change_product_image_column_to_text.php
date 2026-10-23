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
        // Image URLs (e.g. Firebase Storage links) can exceed 255 chars,
        // same issue as the users.avatar column.
        Schema::table('products', function (Blueprint $table) {
            $table->text('image')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image')->change();
        });
    }
};