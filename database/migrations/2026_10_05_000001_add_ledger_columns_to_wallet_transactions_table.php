<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Evolves wallet_transactions into a proper ledger: every
     * credit/debit records the balance before/after, a unique
     * reference and a status. The original title/image_url
     * columns are kept so the existing Flutter wallet tile
     * keeps working.
     */
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreignId('wallet_id')
                ->nullable()
                ->after('user_id')
                ->constrained('wallets')
                ->cascadeOnDelete();

            $table->decimal('balance_before', 15, 2)->nullable()->after('amount');
            $table->decimal('balance_after', 15, 2)->nullable()->after('balance_before');

            $table->string('reference', 60)->nullable()->unique()->after('balance_after');
            $table->string('description', 255)->nullable()->after('reference');
            $table->string('status', 20)->default('completed')->after('description');
            $table->json('metadata')->nullable()->after('status');

            $table->index(['wallet_id', 'created_at']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex(['wallet_id', 'created_at']);
            $table->dropIndex(['status']);
            $table->dropUnique(['reference']);

            $table->dropColumn([
                'wallet_id',
                'balance_before',
                'balance_after',
                'reference',
                'description',
                'status',
                'metadata',
            ]);
        });
    }
};
