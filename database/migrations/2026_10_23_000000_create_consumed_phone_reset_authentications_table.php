<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumed_phone_reset_authentications', function (Blueprint $table) {
            $table->id();
            $table->string('firebase_uid');
            $table->unsignedBigInteger('auth_time');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['firebase_uid', 'auth_time'],
                'consumed_phone_reset_uid_auth_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumed_phone_reset_authentications');
    }
};
