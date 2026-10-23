<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        if (Schema::hasIndex('users', 'users_full_name_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_full_name_unique');
            });
        }

        if (Schema::hasIndex('users', 'users_nick_name_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_nick_name_unique');
            });
        }
    }

  
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unique('full_name', 'users_full_name_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('nick_name', 'users_nick_name_unique');
        });
    }
};