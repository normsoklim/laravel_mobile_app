<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('avatar')->nullable()->change();
        });

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->text('provider_avatar')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable()->change();
        });

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->string('provider_avatar')->nullable()->change();
        });
    }
};
