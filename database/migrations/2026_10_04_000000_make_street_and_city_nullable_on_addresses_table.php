<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Street and city are optional — an address may be saved
     * from a bare map pin (or typed in) without them, so the
     * columns must accept NULL.
     *
     * Raw SQL is used because doctrine/dbal is not installed,
     * which the Schema builder's ->change() requires.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE addresses ALTER COLUMN street DROP NOT NULL');
        DB::statement('ALTER TABLE addresses ALTER COLUMN city DROP NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE addresses ALTER COLUMN street SET NOT NULL');
        DB::statement('ALTER TABLE addresses ALTER COLUMN city SET NOT NULL');
    }
};
